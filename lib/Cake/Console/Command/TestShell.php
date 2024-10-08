<?php
/**
 * Test Shell
 *
 * This Shell allows the running of test suites via the cake command line
 *
 * CakePHP(tm) Tests <https://book.cakephp.org/2.0/en/development/testing.html>
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://book.cakephp.org/2.0/en/development/testing.html
 * @since         CakePHP(tm) v 1.2.0.4433
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

App::uses('Shell', 'Console');
App::uses('CakeTestSuiteDispatcher', 'TestSuite');
App::uses('CakeTestSuiteCommand', 'TestSuite');
App::uses('CakeTestLoader', 'TestSuite');
App::uses('CakeTestSuite', 'TestSuite');

/**
 * Class TestsShell
 */
class TestShell extends Shell {

/**
 * Dispatcher object for the run.
 */
	protected $_dispatcher = null;

/**
 * Gets the option parser instance and configures it.
 *
 * @return ConsoleOptionParser
 */
	public function getOptionParser(): ConsoleOptionParser {
		$parser = new ConsoleOptionParser($this->name);

		$parser->description(
			__d('cake_console', 'The CakePHP Testsuite allows you to run test cases from the command line')
		)->addArgument('category', [
			'help' => __d('cake_console', 'The category for the test, or test file, to test.'),
			'required' => false,
		])->addArgument('file', [
			'help' => __d('cake_console', 'The path to the file, or test file, to test.'),
			'required' => false,
		])->addOption('log-junit', [
			'help' => __d('cake_console', '<file> Log test execution in JUnit XML format to file.'),
			'default' => false,
		])->addOption('log-json', [
			'help' => __d('cake_console', '<file> Log test execution in JSON format to file.'),
			'default' => false,
		])->addOption('log-tap', [
			'help' => __d('cake_console', '<file> Log test execution in TAP format to file.'),
			'default' => false,
		])->addOption('log-dbus', [
			'help' => __d('cake_console', 'Log test execution to DBUS.'),
			'default' => false,
		])->addOption('coverage-html', [
			'help' => __d('cake_console', '<dir> Generate code coverage report in HTML format.'),
			'default' => false,
		])->addOption('coverage-clover', [
			'help' => __d('cake_console', '<file> Write code coverage data in Clover XML format.'),
			'default' => false,
		])->addOption('coverage-text', [
			'help' => __d('cake_console', 'Output code coverage report in Text format.'),
			'boolean' => true,
		])->addOption('testdox-html', [
			'help' => __d('cake_console', '<file> Write agile documentation in HTML format to file.'),
			'default' => false,
		])->addOption('testdox-text', [
			'help' => __d('cake_console', '<file> Write agile documentation in Text format to file.'),
			'default' => false,
		])->addOption('filter', [
			'help' => __d('cake_console', '<pattern> Filter which tests to run.'),
			'default' => false,
		])->addOption('group', [
			'help' => __d('cake_console', '<name> Only runs tests from the specified group(s).'),
			'default' => false,
		])->addOption('exclude-group', [
			'help' => __d('cake_console', '<name> Exclude tests from the specified group(s).'),
			'default' => false,
		])->addOption('list-groups', [
			'help' => __d('cake_console', 'List available test groups.'),
			'boolean' => true,
		])->addOption('loader', [
			'help' => __d('cake_console', 'TestSuiteLoader implementation to use.'),
			'default' => false,
		])->addOption('repeat', [
			'help' => __d('cake_console', '<times> Runs the test(s) repeatedly.'),
			'default' => false,
		])->addOption('tap', [
			'help' => __d('cake_console', 'Report test execution progress in TAP format.'),
			'boolean' => true,
		])->addOption('testdox', [
			'help' => __d('cake_console', 'Report test execution progress in TestDox format.'),
			'default' => false,
			'boolean' => true,
		])->addOption('no-colors', [
			'help' => __d('cake_console', 'Do not use colors in output.'),
			'boolean' => true,
		])->addOption('stderr', [
			'help' => __d('cake_console', 'Write to STDERR instead of STDOUT.'),
			'boolean' => true,
		])->addOption('stop-on-error', [
			'help' => __d('cake_console', 'Stop execution upon first error or failure.'),
			'boolean' => true,
		])->addOption('stop-on-failure', [
			'help' => __d('cake_console', 'Stop execution upon first failure.'),
			'boolean' => true,
		])->addOption('stop-on-skipped', [
			'help' => __d('cake_console', 'Stop execution upon first skipped test.'),
			'boolean' => true,
		])->addOption('stop-on-incomplete', [
			'help' => __d('cake_console', 'Stop execution upon first incomplete test.'),
			'boolean' => true,
		])->addOption('strict', [
			'help' => __d('cake_console', 'Mark a test as incomplete if no assertions are made.'),
			'boolean' => true,
		])->addOption('wait', [
			'help' => __d('cake_console', 'Waits for a keystroke after each test.'),
			'boolean' => true,
		])->addOption('process-isolation', [
			'help' => __d('cake_console', 'Run each test in a separate PHP process.'),
			'boolean' => true,
		])->addOption('no-globals-backup', [
			'help' => __d('cake_console', 'Do not backup and restore $GLOBALS for each test.'),
			'boolean' => true,
		])->addOption('static-backup', [
			'help' => __d('cake_console', 'Backup and restore static attributes for each test.'),
			'boolean' => true,
		])->addOption('syntax-check', [
			'help' => __d('cake_console', 'Try to check source files for syntax errors.'),
			'boolean' => true,
		])->addOption('bootstrap', [
			'help' => __d('cake_console', '<file> A "bootstrap" PHP file that is run before the tests.'),
			'default' => false,
		])->addOption('configuration', [
			'help' => __d('cake_console', '<file> Read configuration from XML file.'),
			'default' => false,
		])->addOption('no-configuration', [
			'help' => __d('cake_console', 'Ignore default configuration file (phpunit.xml).'),
			'boolean' => true,
		])->addOption('include-path', [
			'help' => __d('cake_console', '<path(s)> Prepend PHP include_path with given path(s).'),
			'default' => false,
		])->addOption('directive', [
			'help' => __d('cake_console', 'key[=value] Sets a php.ini value.'),
			'short' => 'd',
			'default' => false,
		])->addOption('fixture', [
			'help' => __d('cake_console', 'Choose a custom fixture manager.'),
		])->addOption('debug', [
			'help' => __d('cake_console', 'More verbose output.'),
		]);

		return $parser;
	}

/**
 * Initialization method installs PHPUnit and loads all plugins
 *
 * @return void
 * @throws Exception
 */
	public function initialize(): void {
		$this->_dispatcher = new CakeTestSuiteDispatcher();
		$success = $this->_dispatcher->loadTestFramework();
		if (!$success) {
			throw new Exception(__d('cake_dev', 'Please install PHPUnit framework v9.6 <info>(http://www.phpunit.de)</info>'));
		}
	}

/**
 * Parse the CLI options into an array CakeTestDispatcher can use.
 *
 * @return array|null Array of params for CakeTestDispatcher or null.
 */
	protected function _parseArgs() {
		if (empty($this->args)) {
			return null;
		}
		$params = [
			'core' => false,
			'app' => false,
			'plugin' => null,
			'output' => 'text',
		];

		if (strpos($this->args[0], '.php')) {
			$category = $this->_mapFileToCategory($this->args[0]);
			$params['case'] = $this->_mapFileToCase($this->args[0], $category);
		} else {
			$category = $this->args[0];
			if (isset($this->args[1])) {
				$params['case'] = $this->args[1];
			}
		}

		if ($category === 'core') {
			$params['core'] = true;
		} else if ($category === 'app') {
			$params['app'] = true;
		} else {
			$params['plugin'] = $category;
		}

		return $params;
	}

/**
 * Converts the options passed to the shell as options for the PHPUnit cli runner
 *
 * @return array Array of params for CakeTestDispatcher
 */
	protected function _runnerOptions(): array {
		$options = [];
		$params = $this->params;
		unset($params['help']);
		unset($params['quiet']);

		if (!empty($params['no-colors'])) {
			unset($params['no-colors'], $params['colors']);
		} else {
			$params['colors'] = true;
		}

		foreach ($params as $param => $value) {
			if ($value === false) {
				continue;
			}
			if ($param === 'directive') {
				$options[] = '-d';
			} else {
				$options[] = '--' . $param;
			}
			if (is_string($value)) {
				$options[] = $value;
			}
		}
		return $options;
	}

/**
 * Main entry point to this shell
 *
 * @return mixed
 */
	public function main() {
		$this->out(__d(
				'cake_console',
				'CakePHP Test Shell                     <info>NEW VERSION</info>')
		);
		$this->hr();

		$args = $this->_parseArgs();

		if (empty($args['case'])) {
			return $this->available();
		}

		$this->_run($args, $this->_runnerOptions());
	}

/**
 * Runs the test case from $runnerArgs
 *
 * @param array $runnerArgs list of arguments as obtained from _parseArgs()
 * @param array $options list of options as constructed by _runnerOptions()
 *
 * @return void
 */
	protected function _run(array $runnerArgs, array $options = []): void {
		restore_error_handler();
		restore_error_handler();

		$testCli = new CakeTestSuiteCommand('CakeTestLoader', $runnerArgs);
		$testCli->run(array_merge(
			[$options],
			['--bootstrap', 'Test/bootstrap.php', $runnerArgs['case']],
		));
	}

/**
 * Shows a list of available test cases and gives the option to run one of them
 *
 * @return void
 */
	public function available(): void {
		$params = $this->_parseArgs();
		$testCases = CakeTestLoader::generateTestList($params);
		$app = $params['app'] ?? null;
		$plugin = $params['plugin'] ?? null;

		$title = "Core Test Cases:";
		$category = 'core';
		if ($app) {
			$title = "App Test Cases:";
			$category = 'app';
		} else if ($plugin) {
			$title = Inflector::humanize($plugin) . " Test Cases:";
			$category = $plugin;
		}

		if (empty($testCases)) {
			$this->out(__d('cake_console', "No test cases available \n\n"));
			$this->out($this->OptionParser->help());
			return;
		}

		$this->out($title);
		$i = 1;
		$cases = [];
		foreach ($testCases as $testCase) {
			$case = str_replace('Test.php', '', $testCase);
			$this->out("[$i] $case");
			$cases[$i] = $case;
			$i++;
		}

		while ($choice = $this->in(__d('cake_console', 'What test case would you like to run?'), null, 'q')) {
			if (is_numeric($choice) && isset($cases[$choice])) {
				$this->args[0] = $category;
				$this->args[1] = $cases[$choice];
				$this->_run($this->_parseArgs(), $this->_runnerOptions());
				break;
			}

			if (is_string($choice) && in_array($choice, $cases)) {
				$this->args[0] = $category;
				$this->args[1] = $choice;
				$this->_run($this->_parseArgs(), $this->_runnerOptions());
				break;
			}

			if ($choice === 'q') {
				break;
			}
		}
	}

/**
 * Find the test case for the passed file. The file could itself be a test.
 *
 * @param string $file The file to map.
 * @param string $category The test file category.
 * @param bool $throwOnMissingFile Whether or not to throw an exception.
 *
 * @return array|false
 * @throws Exception
 */
	protected function _mapFileToCase(string $file, string $category, bool $throwOnMissingFile = true) {
		if (!$category || (substr($file, -4) !== '.php')) {
			return false;
		}

		$_file = realpath($file);
		if ($_file) {
			$file = $_file;
		}

		$testFile = $testCase = null;
		$testCaseFolder = str_replace(APP, '', APP_TEST_CASES);
		if (preg_match('@Test[\\\/]@', $file)) {
			if (substr($file, -8) === 'Test.php') {
				$testCase = substr($file, 0, -8);
				$testCase = str_replace(DS, '/', $testCase);
				$testCaseFolderEscaped = str_replace('/', '\/', $testCaseFolder);
				$testCase = preg_replace('@.*' . $testCaseFolderEscaped . '\/@', '', $testCase);
				if (!empty($testCase)) {
					if ($category === 'core') {
						$testCase = str_replace('lib/Cake', '', $testCase);
					}
					return $testCase;
				}
				throw new Exception(__d('cake_dev', 'Test case %s cannot be run via this shell', $testFile));
			}
		}

		$file = substr($file, 0, -4);
		if ($category === 'core') {

			$testCase = str_replace(DS, '/', $file);
			$testCase = preg_replace('@.*lib/Cake/@', '', $file);
			$testCase[0] = strtoupper($testCase[0]);
			$testFile = CAKE . 'Test/Case/' . $testCase . 'Test.php';

			if (!file_exists($testFile) && $throwOnMissingFile) {
				throw new Exception(__d('cake_dev', 'Test case %s not found', $testFile));
			}

			return $testCase;
		}

		if ($category === 'app') {
			$testFile = str_replace(APP, APP_TEST_CASES . '/', $file) . 'Test.php';
		} else {
			$testFile = preg_replace(
				"@((?:plugins|Plugin)[\\/]{$category}[\\/])(.*)$@",
				'\1' . $testCaseFolder . '/\2Test.php',
				$file
			);
		}

		if (!file_exists($testFile) && $throwOnMissingFile) {
			throw new Exception(__d('cake_dev', 'Test case %s not found', $testFile));
		}

		$testCase = substr($testFile, 0, -8);
		$testCase = str_replace(DS, '/', $testCase);
		$testCase = preg_replace('@.*' . $testCaseFolder . '/@', '', $testCase);
		return $testCase;
	}

/**
 * For the given file, what category of test is it? returns app, core or the name of the plugin
 *
 * @param string $file The file to map.
 *
 * @return string
 */
	protected function _mapFileToCategory(string $file): string {
		$_file = realpath($file);
		if ($_file) {
			$file = $_file;
		}

		$file = str_replace(DS, '/', $file);
		if (strpos($file, 'lib/Cake/') !== false) {
			return 'core';
		} else if (preg_match('@(?:plugins|Plugin)/([^/]*)@', $file, $match)) {
			return $match[1];
		}

		return 'app';
	}
}
