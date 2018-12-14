<?php
declare(strict_types=1);

namespace BehatRemoteCodeCoverage;

use Behat\Mink\Session;
use RuntimeException;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use Webmozart\Assert\Assert;
use Behat\Behat\EventDispatcher\Event\AfterFeatureTested;
use Behat\Behat\EventDispatcher\Event\FeatureTested;
use Behat\Behat\EventDispatcher\Event\ScenarioLikeTested;
use Behat\Behat\EventDispatcher\Event\ScenarioTested;
use Behat\Mink\Mink;
use Behat\Testwork\EventDispatcher\Event\AfterSuiteTested;
use Behat\Testwork\EventDispatcher\Event\BeforeSuiteTested;
use Behat\Testwork\EventDispatcher\Event\SuiteTested;
use LiveCodeCoverage\Storage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class RemoteCodeCoverageListener implements EventSubscriberInterface
{
    /**
     * @var Mink
     */
    private $mink;

    /**
     * @var string
     */
    private $targetDirectory;

    /**
     * @var string
     */
    private $splitBy = 'suite';

    /**
     * @var string
     */
    private $coverageGroup;

    /**
     * @var bool
     */
    private $coverageEnabled = false;

    /**
     * @var string
     */
    private $defaultMinkSession;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var string
     */
    private $minkSession;

    public function __construct(Mink $mink, $defaultMinkSession, $baseUrl, $targetDirectory, $splitBy)
    {
        $this->mink = $mink;

        Assert::string($defaultMinkSession, 'Default Mink session should be a string');
        $this->defaultMinkSession = $defaultMinkSession;

        Assert::string($baseUrl);
        $this->baseUrl = $baseUrl;

        Assert::string($targetDirectory, 'Coverage target directory should be a string');
        $this->targetDirectory = $targetDirectory;

        Assert::string($splitBy, 'Split coverage files by should be a string');
        $this->splitBy = $splitBy;
    }

    public static function getSubscribedEvents()
    {
        return [
            SuiteTested::BEFORE => 'beforeSuite',
            ScenarioTested::BEFORE => 'beforeScenario',
            FeatureTested::AFTER => 'afterFeature',
            SuiteTested::AFTER => 'afterSuite'
        ];
    }

    public function beforeSuite(BeforeSuiteTested $event)
    {
        $this->coverageEnabled = $event->getSuite()->hasSetting('remote_coverage_enabled')
            && $event->getSuite()->getSetting('remote_coverage_enabled');

        if (!$this->coverageEnabled) {
            return;
        }

        $this->minkSession = $event->getSuite()->hasSetting('mink_session') ?
            $event->getSuite()->getSetting('mink_session') : $this->defaultMinkSession;
        $this->coverageGroup = uniqid($event->getSuite()->getName(), true);
    }

    public function beforeScenario(ScenarioLikeTested $event)
    {
        if (!$this->coverageEnabled) {
            return;
        }

        $coverageId = $event->getFeature()->getFile() . ':' . $event->getNode()->getLine();

        $this->getMinkSession()->setCookie('collect_code_coverage', true);
        $this->getMinkSession()->setCookie('coverage_group', $this->coverageGroup);
        $this->getMinkSession()->setCookie('coverage_id', $coverageId);
    }

    public function afterFeature(AfterFeatureTested $event)
    {
        if (!$this->coverageEnabled || 'feature' !== $this->splitBy) {
            return;
        }

        $parts = pathinfo($event->getFeature()->getFile());
        Storage::storeCodeCoverage($this->getCoverage(), $this->targetDirectory, sprintf('%s-%s', basename($parts['dirname']), $parts['filename']));
    }

    public function afterSuite(AfterSuiteTested $event)
    {
        if (!$this->coverageEnabled) {
            return;
        }

        if ('suite' === $this->splitBy) {
            Storage::storeCodeCoverage($this->getCoverage(), $this->targetDirectory, $event->getSuite()->getName());
        }

        $this->reset();
    }

    private function reset()
    {
        $this->coverageGroup = null;
        $this->coverageEnabled = false;
    }

    /**
     * @return Session
     */
    private function getMinkSession()
    {
        return $this->mink->getSession($this->minkSession);
    }

    /**
     * @return mixed
     * @throws RuntimeException
     */
    private function getCoverage()
    {
        $requestUrl = $this->baseUrl . '/?export_code_coverage=true&coverage_group=' . urlencode($this->coverageGroup);
        $response = file_get_contents($requestUrl);
        $coverage = unserialize($response);

        if (!$coverage instanceof CodeCoverage) {
            throw new RuntimeException(sprintf(
                'The response for "%s" did not contain a serialized CodeCoverage object: %s',
                $requestUrl,
                $response
            ));
        }

        return $coverage;
    }
}
