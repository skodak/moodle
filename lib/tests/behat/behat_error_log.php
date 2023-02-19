<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use Behat\Testwork\Suite\Suite;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioNode;

require_once(__DIR__ . '/../../../lib/behat/behat_base.php');

/**
 * Behat error detection hooks
 *
 * @package    core
 * @category   test
 * @copyright  2023 Open LMS
 * @author     Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_error_log extends behat_base {
    /** @var string */
    protected static $errorlog;
    /** @var int */
    protected static $errorlogposition;
    /** @var Suite */
    protected static $lastsuite;
    /** @var FeatureNode */
    protected static $lastfeature;
    /** @var ScenarioNode */
    protected static $lastscenario;
    /** @var bool */
    protected static $ignoreerrors = false;

    /**
     * Ignore errors for a moment.
     *
     * NOTE: this is automatically cancelled at the start of next scenario.
     *
     * @When /^I start ignoring errors detected by behat$/
     */
    public function start_ignoring_errors() {
        self::$ignoreerrors = true;
    }

    /**
     * Ignore errors for a moment.
     *
     * @When /^I stop ignoring errors detected by behat$/
     */
    public function stop_ignoring_errors() {
        self::$ignoreerrors = false;
        self::get_log_error();
    }

    /**
     * Are the errors supposed to be ignored?
     *
     * @return bool
     */
    public static function is_ignoring_errors(): bool {
        return self::$ignoreerrors;
    }

    /**
     * Create or truncate PHP error log file at the very start of behat run.
     */
    public static function init_error_log(): void {
        global $CFG;

        self::$errorlog = "$CFG->dataroot/behat/error.log";
        self::$errorlogposition = 0;

        $fp = fopen(self::$errorlog, 'w');
        fclose($fp);
        // Log file is written both from web and CLI, so use more open permissions.
        chmod(self::$errorlog, 0666);

        ini_set('error_log', self::$errorlog);
        ini_set('log_errors', '1');
    }

    /**
     * Log scenario start.
     *
     * @BeforeScenario
     *
     * @param BeforeScenarioScope $scope
     */
    public static function before_scenario(BeforeScenarioScope $scope) {
        self::$ignoreerrors = false;

        if (!self::$lastsuite || self::$lastsuite !== $scope->getSuite()) {
            self::$lastsuite = $scope->getSuite();
            error_log('');
            error_log('Behat suite: ' . self::$lastsuite->getName());
        }

        if (!self::$lastfeature || self::$lastfeature !== $scope->getFeature()) {
            self::$lastfeature = $scope->getFeature();
            error_log('');
            error_log('Feature: ' . $scope->getFeature()->getTitle() . ' # ' . $scope->getFeature()->getFile());
        }

        self::$lastscenario = $scope->getScenario();
        error_log('');
        error_log('Scenario: ' . $scope->getScenario()->getTitle());
    }

    /**
     * Check all types of problems after every step.
     *
     * @AfterStep
     *
     * @param AfterStepScope $scope
     */
    public function after_step(AfterStepScope $scope) {
        if ($scope->getTestResult()->getResultCode() === \Behat\Testwork\Tester\Result\TestResult::FAILED) {
            error_log('Failed step: ' . $scope->getStep()->getText() . ' # ' . $scope->getFeature()->getFile() . ':' . $scope->getStep()->getLine());
            $result = $scope->getTestResult();
            if ($result instanceof \Behat\Testwork\Tester\Result\ExceptionResult) {
                if ($result->hasException()) {
                    $ex = $result->getException();
                    error_log('Exception: ' . $ex->getMessage() . ' (' . get_class($ex) . ')');
                }
            }
            // Do not throw Exceptions here, this will already fail.
            self::get_log_error();
        } else {
            $error = self::get_log_error();
            if ($error !== null) {
                $step = $scope->getStep();
                $info = [];
                $info[] = 'Scenario: '.self::$lastscenario->getTitle();
                $info[] = '  '.$step->getText().' # '.self::$lastfeature->getFile().':'.$step->getLine();
                $info[] = '    '.$error;
                throw new Exception(implode(PHP_EOL, $info));
            }
        }
    }

    /**
     * Check error log if there are any errors, notices, exceptions or debugging messages.
     *
     * @return null|string
     */
    public static function get_log_error(): ?string {
        if (!self::$errorlog) {
            return null;
        }
        if (!$fp = fopen(self::$errorlog, 'r')) {
            throw new Exception('Error reading behat error log file');
        }
        // Read logs from last position until the very end.
        fseek($fp, self::$errorlogposition);
        $logs = fread($fp, 999999);
        self::$errorlogposition += strlen($logs);
        fclose($fp);

        if (self::$ignoreerrors) {
            return null;
        }

        if (preg_match('/^\[[^]]+] [^:]+ failed: (.*)$/ms', $logs, $matches)) {
            return ('Behat found failure in error log: ' . "\n" . $matches[0]);
        } else if (preg_match('/^\[[^]]+] (PHP [^:]+): (.*)$/ms', $logs, $matches)) {
            return ('Behat found ' . $matches[1] . ' in error log: ' . "\n" . $matches[0]);
        } else if (preg_match('/^\[[^]]+] [^:]+ exception handler: (.*)$/ms', $logs, $matches)) {
            return ('Behat found unhandled Exception in error log: ' . "\n" . $matches[0]);
        } else if (preg_match('/^\[[^]]+] Debugging: (.*)$/ms', $logs, $matches)) {
            return ('Behat found debugging info in error log: ' . "\n" . $matches[0]);
        } else {
            // Fatal errors are not logged, let's hope it gets detected by other means.
            return null;
        }
    }
}
