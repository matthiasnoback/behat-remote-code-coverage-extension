# Behat remote code coverage extension

This extension can be used to collect code coverage data from the web server that's called by Mink while running Behat.

To use this extension, enable it under `extensions` and for every suite that needs remote code coverage collection, set `remote_coverage_enabled` to `true`.

```yaml
default:
    extensions:
        BehatRemoteCodeCoverage\RemoteCodeCoverageExtension:
            target_directory: '%paths.base%/var/coverage'
    suites:
        default:
            remote_coverage_enabled: true
```

Now modify the front controller of your web application to look like this:

```php
use LiveCodeCoverage\RemoteCodeCoverage;

$shutDownCodeCoverage = RemoteCodeCoverage::bootstrap(
    (bool)getenv('CODE_COVERAGE_ENABLED'),
    sys_get_temp_dir(),
    __DIR__ . '/../phpunit.xml.dist'
);

// Run your web application now...

// This will save and store collected coverage data:
$shutDownCodeCoverage();
```

Make sure to modify the call to `RemoteCodeCoverage::bootstrap()` if needed:

1. Provide your own logic to determine if code coverage should be enabled in the first place (this example uses an environment variable for that). This is important for security reasons. It helps you make sure that the production server won't expose any collected coverage data.
2. Provide your own directory for storing the coverage data files (`.cov`).
3. Provide the path to your own `phpunit.xml(.dist)` file. This file is only used for its [code coverage filter configuration](https://phpunit.de/manual/current/en/appendixes.configuration.html#appendixes.configuration.whitelisting-files).

After a test run, the extension makes a special call (`/?code_coverage_export=true&...`) to the web application. The response to this call  contains the serialized code coverage data. It will be stored as a file in `target_directory`, named after the test suite itself, e.g. `default.cov`.

You can use these `.cov` files to generate nice reports, using [`phpcov`](https://github.com/sebastianbergmann/phpcov).

You could even configure PHPUnit to generate a `.cov` file in the same directory, so you can combine coverage data from PHPUnit and Behat in one report.

To (also) generate (local) code coverage during a Behat test run, use the [`LocalCodeCoverageExtension`](https://github.com/matthiasnoback/behat-local-code-coverage-extension/).
