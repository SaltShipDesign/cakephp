<?php

use PHPUnit\Framework\TestSuite;
use PHPUnit\Runner\Version;
use PHPUnit\TextUI\Command;
use PHPUnit\TextUI\TestRunner;
use PHPUnit\Util\TextTestListRenderer;
use PHPUnit\Util\XmlTestListRenderer;

App::uses('CakeTestRunner', 'TestSuite');
App::uses('ControllerTestCase', 'TestSuite');
App::uses('CakeTestModel', 'TestSuite/Fixture');

class CakeTestSuiteCommand extends Command {

	private $versionStringPrinted = false;
	protected $_params = [];

	public function __construct($loader, array $params = []) {
		if ($loader && !class_exists($loader)) {
			throw new MissingTestLoaderException(['class' => $loader]);
		}
		$this->arguments['loader'] = $loader;
		$this->arguments['test'] = $params['case'];
		$this->arguments['testFile'] = $params;
		$this->longOptions['fixture='] = 'handleFixture';
		$this->longOptions['output='] = 'handleReporter';
		$this->_params = $params;
	}

/**
 * Hack to get around PHPUnit having a hard coded class name for the Runner. :(
 *
 * @throws \PHPUnit\TextUI\Exception
 */
	public function run(array $argv, bool $exit = true): int {
		$this->handleArguments($argv);

		$runner = $this->getRunner($this->arguments['loader']);

		if ($this->arguments['test'] instanceof TestSuite) {
			$suite = $this->arguments['test'];
		} else {
			$suite = $runner->getTest(
				$this->arguments['test'],
				$this->arguments['testSuffixes'],
			);
		}

		if ($this->arguments['listGroups']) {
			return $this->handleListGroups($suite, $exit);
		}

		if ($this->arguments['listSuites']) {
			return $this->handleListSuites($exit);
		}

		if ($this->arguments['listTests']) {
			return $this->handleListTests($suite, $exit);
		}

		if ($this->arguments['listTestsXml']) {
			return $this->handleListTestsXml($suite, $this->arguments['listTestsXml'], $exit);
		}

		unset($this->arguments['test'], $this->arguments['testFile']);

		try {
			$runner->run($suite, $this->arguments, $exit);
		} catch (Throwable $t) {
			print $t->getMessage() . PHP_EOL;
		}

		$return = TestRunner::FAILURE_EXIT;

		if (isset($result) && $result->wasSuccessful()) {
			$return = TestRunner::SUCCESS_EXIT;
		} elseif (!isset($result) || $result->errorCount() > 0) {
			$return = TestRunner::EXCEPTION_EXIT;
		}

		if ($exit) {
			exit($return);
		}

		return $return;
	}

/**
 * Get runner
 *
 * @param $loader Test loader
 * @return CakeTestRunner
 */
	public function getRunner($loader): CakeTestRunner {
		return new CakeTestRunner($loader, $this->_params);
	}

/**
 * Handle fixture
 *
 * @param string $class Class name
 *
 * @return void
 */
	public function handleFixture(string $class) {
		$this->arguments['fixtureManager'] = $class;
	}

/**
 * Handle reporter
 *
 * @param string $reporter Reporter
 * @return mixed
 */
	public function handleReporter(string $reporter) {
		$object = null;

		$reporter = ucwords($reporter);
		$coreClass = 'Cake' . $reporter . 'Reporter';
		App::uses($coreClass, 'TestSuite/Reporter');

		$appClass = $reporter . 'Reporter';
		App::uses($appClass, 'TestSuite/Reporter');

		if (!class_exists($appClass)) {
			$object = new $coreClass(null, $this->_params);
		} else {
			$object = new $appClass(null, $this->_params);
		}
		return $this->arguments['printer'] = $object;
	}

/**
 * Print version
 *
 * @return void
 */
	private function printVersionString(): void {
		if ($this->versionStringPrinted) {
			return;
		}

		print Version::getVersionString() . PHP_EOL . PHP_EOL;

		$this->versionStringPrinted = true;
	}

/**
 * Warn about conflicting options
 *
 * @psalm-param "listGroups"|"listSuites"|"listTests"|"listTestsXml"|"filter"|"groups"|"excludeGroups"|"testsuite" $key
 * @psalm-param list<"listGroups"|"listSuites"|"listTests"|"listTestsXml"|"filter"|"groups"|"excludeGroups"|"testsuite"> $keys
 */
	private function warnAboutConflictingOptions(string $key, array $keys): void {
		$warningPrinted = false;

		foreach ($keys as $_key) {
			if (!empty($this->arguments[$_key])) {
				printf(
					'The %s and %s options cannot be combined, %s is ignored' . PHP_EOL,
					$this->mapKeyToOptionForWarning($_key),
					$this->mapKeyToOptionForWarning($key),
					$this->mapKeyToOptionForWarning($_key),
				);

				$warningPrinted = true;
			}
		}

		if ($warningPrinted) {
			print PHP_EOL;
		}
	}

/**
 * Handle groups
 *
 * @param TestSuite $suite Suite
 * @param bool $exit Exit code
 * @return int
 */
	private function handleListGroups(TestSuite $suite, bool $exit): int {
		$this->printVersionString();

		$this->warnAboutConflictingOptions(
			'listGroups',
			[
				'filter',
				'groups',
				'excludeGroups',
				'testsuite',
			],
		);

		print 'Available test group(s):' . PHP_EOL;

		$groups = $suite->getGroups();
		sort($groups);

		foreach ($groups as $group) {
			if (strpos($group, '__phpunit_') === 0) {
				continue;
			}

			printf(
				' - %s' . PHP_EOL,
				$group,
			);
		}

		if ($exit) {
			exit(TestRunner::SUCCESS_EXIT);
		}

		return TestRunner::SUCCESS_EXIT;
	}

/**
 * Handle suite
 *
 * @param bool $exit
 * @return int
 */
	private function handleListSuites(bool $exit): int {
		$this->printVersionString();

		$this->warnAboutConflictingOptions(
			'listSuites',
			[
				'filter',
				'groups',
				'excludeGroups',
				'testsuite',
			],
		);

		print 'Available test suite(s):' . PHP_EOL;

		foreach ($this->arguments['configurationObject']->testSuite() as $testSuite) {
			printf(
				' - %s' . PHP_EOL,
				$testSuite->name(),
			);
		}

		if ($exit) {
			exit(TestRunner::SUCCESS_EXIT);
		}

		return TestRunner::SUCCESS_EXIT;
	}

/**
 * Handle tests
 *
 * @param TestSuite $suite Suite
 * @param bool $exit Exit code
 * @return int
 */
	private function handleListTests(TestSuite $suite, bool $exit): int {
		$this->printVersionString();

		$this->warnAboutConflictingOptions(
			'listTests',
			[
				'filter',
				'groups',
				'excludeGroups',
			],
		);

		$renderer = new TextTestListRenderer;

		print $renderer->render($suite);

		if ($exit) {
			exit(TestRunner::SUCCESS_EXIT);
		}

		return TestRunner::SUCCESS_EXIT;
	}

/**
 * Handle list test xml
 *
 * @param TestSuite $suite Suite
 * @param string $target Target
 * @param bool $exit Exit code
 * @return int
 */
	private function handleListTestsXml(TestSuite $suite, string $target, bool $exit): int {
		$this->printVersionString();

		$this->warnAboutConflictingOptions(
			'listTestsXml',
			[
				'filter',
				'groups',
				'excludeGroups',
			],
		);

		$renderer = new XmlTestListRenderer;

		file_put_contents($target, $renderer->render($suite));

		printf(
			'Wrote list of tests that would have been run to %s' . PHP_EOL,
			$target,
		);

		if ($exit) {
			exit(TestRunner::SUCCESS_EXIT);
		}

		return TestRunner::SUCCESS_EXIT;
	}

/**
 * Map Key To Option For Warning
 *
 * @psalm-param "listGroups"|"listSuites"|"listTests"|"listTestsXml"|"filter"|"groups"|"excludeGroups"|"testsuite" $key
 * @return string
 */
	private function mapKeyToOptionForWarning(string $key): string {
		switch ($key) {
			case 'listGroups':
				return '--list-groups';

			case 'listSuites':
				return '--list-suites';

			case 'listTests':
				return '--list-tests';

			case 'listTestsXml':
				return '--list-tests-xml';

			case 'filter':
				return '--filter';

			case 'groups':
				return '--group';

			case 'excludeGroups':
				return '--exclude-group';

			case 'testsuite':
				return '--testsuite';
		}
		return '';
	}
}
