<?php
declare(strict_types=1);

namespace BehatRemoteCodeCoverage;

use Behat\Behat\EventDispatcher\Event\AfterScenarioTested;
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
    private $baseUrl;

    public function __construct(Mink $mink, $baseUrl, $targetDirectory, $splitBy)
    {
        $this->mink = $mink;

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
            ScenarioTested::AFTER => 'afterScenario',
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

        $this->coverageGroup = uniqid($event->getSuite()->getName(), true);
    }

    public function beforeScenario(ScenarioLikeTested $event)
    {
        if (!$this->coverageEnabled) {
            return;
        }

        $coverageId = $event->getFeature()->getFile() . ':' . $event->getNode()->getLine();

        $minkSession = $this->mink->getSession();

        if (!$minkSession->isStarted()) {
            $minkSession->start();
        }

        $minkSession->setCookie('collect_code_coverage', true);
        $minkSession->setCookie('coverage_group', $this->coverageGroup);
        $minkSession->setCookie('coverage_id', $coverageId);
    }

    public function afterScenario(AfterScenarioTested $event)
    {
        if (!$this->coverageEnabled || 'scenario' !== $this->splitBy) {
            return;
        }

        $parts = pathinfo($event->getFeature()->getFile());
        Storage::storeCodeCoverage($this->getCoverage(), $this->targetDirectory, sprintf('%s-%s_%s', basename($parts['dirname']), $parts['filename'], $event->getNode()->getLine()));
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
