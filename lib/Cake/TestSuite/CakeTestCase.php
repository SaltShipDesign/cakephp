<?php

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;

App::uses('CakeFixtureManager', 'TestSuite/Fixture');
App::uses('CakeTestFixture', 'TestSuite/Fixture');

abstract class CakeTestCase extends TestCase {

/**
 * The class responsible for managing the creation, loading and removing of fixtures
 */
	public $fixtureManager = null;

/**
 * By default, all fixtures attached to this class will be truncated and reloaded after each test.
 * Set this to false handle manually
 */
	public $autoFixtures = true;

/**
 * Control table create/drops on each test method.
 *
 * Set this to false avoid tables to be dropped if they already exist
 * between each test method. Tables will still be dropped at the
 * end of each test runner execution.
 */
	public $dropTables = true;

/**
 * Configure values to restore at end of test.
 */
	protected $_configure = [];

/**
 * Pristine values of specific Configure keys captured at the first test setUp.
 * Used to undo per-test leaks for keys that tests routinely mutate without
 * restoring (e.g. `debug` written to 0 by a test that then throws via
 * @expectedException, skipping its own restore).
 *
 * @var array|null
 */
	protected static $_pristineConfigureKeys = null;

/**
 * Path settings to restore at the end of the test.
 */
	protected $_pathRestore = [];

/**
 * Runs the test case and collects the results in a TestResult object.
 * If no TestResult object is passed a new one will be created.
 * This method is run for each test method in this class
 *
 * @param TestResult|null $result The test result object
 *
 * @return TestResult
 */
	public function run(TestResult $result = null): TestResult {
		$level = ob_get_level();

		if (!empty($this->fixtureManager)) {
			$this->fixtureManager->load($this);
		}
		$result = parent::run($result);
		if (!empty($this->fixtureManager)) {
			$this->fixtureManager->unload($this);
			unset($this->fixtureManager, $this->db);
		}

		for ($i = ob_get_level(); $i < $level; ++$i) {
			ob_start();
		}

		return $result;
	}

/**
 * Called when a test case method is about to start (to be overridden when needed.)
 *
 * @param string $method Test method about to get executed.
 *
 * @return void
 */
	public function startTest(string $method) {
	}

/**
 * Called when a test case method has been executed (to be overridden when needed.)
 *
 * @param string $method Test method about that was executed.
 *
 * @return void
 */
	public function endTest(string $method) {
	}

/**
 * Overrides SimpleTestCase::skipIf to provide a boolean return value
 *
 * @param bool $shouldSkip Whether or not the test should be skipped.
 * @param string $message The message to display.
 *
 * @return bool
 */
	public function skipIf($shouldSkip, string $message = ''): bool {
		$shouldSkip = (bool)$shouldSkip;
		if ($shouldSkip) {
			$this->markTestSkipped($message);
		}
		return $shouldSkip;
	}

/**
 * Backwards-compatible assertContains that handles string haystacks.
 * PHPUnit 9 split string-contains into assertStringContainsString; CakePHP 2.x
 * tests still pass strings to assertContains. Dispatch by haystack type.
 *
 * @param mixed $needle
 * @param mixed $haystack
 * @param string $message
 * @return void
 */
	public static function assertContains($needle, $haystack, string $message = ''): void {
		if (is_string($haystack)) {
			static::assertStringContainsString((string)$needle, $haystack, $message);
			return;
		}
		parent::assertContains($needle, $haystack, $message);
	}

/**
 * Polyfill for the pre-PHPUnit 6 setExpectedException() method, removed
 * in PHPUnit 9. Some CakePHP 2.x tests still call it.
 *
 * @param string $exceptionName
 * @param string|null $exceptionMessage
 * @param int|null $exceptionCode
 * @return void
 */
	public function setExpectedException($exceptionName, $exceptionMessage = null, $exceptionCode = null) {
		$this->expectException($exceptionName);
		if ($exceptionMessage !== null) {
			$this->expectExceptionMessage($exceptionMessage);
		}
		if ($exceptionCode !== null) {
			$this->expectExceptionCode($exceptionCode);
		}
	}

/**
 * Polyfill for attributeEqualTo() constraint, removed in PHPUnit 9.
 * Returns a Callback constraint that reads the named property via reflection
 * (so protected/private fields work) and compares with loose equality.
 *
 * @param string $attributeName
 * @param mixed $value
 * @return \PHPUnit\Framework\Constraint\Callback
 */
	public static function attributeEqualTo(string $attributeName, $value): \PHPUnit\Framework\Constraint\Callback {
		return new \PHPUnit\Framework\Constraint\Callback(function ($other) use ($attributeName, $value) {
			if (!is_object($other)) {
				return false;
			}
			try {
				$property = new \ReflectionProperty($other, $attributeName);
			} catch (\ReflectionException $e) {
				return false;
			}
			$property->setAccessible(true);
			return $property->getValue($other) == $value;
		});
	}

/**
 * Polyfill for assertAttributeEquals, removed in PHPUnit 9.
 * Reads a (possibly protected/private) attribute via reflection and compares.
 *
 * @param mixed $expected
 * @param string $actualAttributeName
 * @param object|string $actualClassOrObject
 * @param string $message
 * @return void
 */
	public static function assertAttributeEquals($expected, string $actualAttributeName, $actualClassOrObject, string $message = ''): void {
		$reflection = new \ReflectionClass($actualClassOrObject);
		$property = $reflection->getProperty($actualAttributeName);
		$property->setAccessible(true);
		$actual = is_object($actualClassOrObject) ? $property->getValue($actualClassOrObject) : $property->getValue();
		static::assertEquals($expected, $actual, $message);
	}

/**
 * Mirror of assertContains: route string haystacks to assertStringNotContainsString.
 *
 * @param mixed $needle
 * @param mixed $haystack
 * @param string $message
 * @return void
 */
	public static function assertNotContains($needle, $haystack, string $message = ''): void {
		if (is_string($haystack)) {
			static::assertStringNotContainsString((string)$needle, $haystack, $message);
			return;
		}
		parent::assertNotContains($needle, $haystack, $message);
	}

/**
 * Setup the test case, backup the static object values so they can be restored.
 * Specifically backs up the contents of Configure and paths in App if they have
 * not already been backed up.
 *
 * @return void
 */
	public function setUp(): void {
		parent::setUp();

		$this->_processExpectedExceptionAnnotations();

		if (!isset($this->_cakeTestServerBackup)) {
			$this->_cakeTestServerBackup = array(
				'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
				'HTTPS' => $_SERVER['HTTPS'] ?? null,
			);
		}

		// Snapshot a couple of routinely-leaked Configure keys ONCE per process.
		// Tests that write Configure::write('debug', 0) and then throw via
		// @expectedException skip their own restore, leaking the change into
		// every subsequent test's `_configure` snapshot. Force-reset those
		// known-leaky keys to their pristine values BEFORE capturing the
		// per-test `_configure` so the per-test restore picks up the right
		// values. Other keys (e.g. Session.handler set via setUpBeforeClass)
		// are intentionally left alone.
		if (static::$_pristineConfigureKeys === null) {
			static::$_pristineConfigureKeys = array(
				'debug' => Configure::read('debug'),
			);
		}
		foreach (static::$_pristineConfigureKeys as $key => $value) {
			if ($value !== null) {
				Configure::write($key, $value);
			}
		}
		if (empty($this->_configure)) {
			$this->_configure = Configure::read();
		}
		if (empty($this->_pathRestore)) {
			$this->_pathRestore = App::paths();
		}
		if (class_exists('Router', false)) {
			Router::reload();
		}
	}

/**
 * Polyfill for @expectedException* docblock annotations (removed in PHPUnit 9).
 * Reads the current test method's docblock and calls expectException()/Message()/Code()
 * accordingly. Lets the ~234 legacy annotations across the test suite keep working
 * without rewriting them as in-body expectException() calls.
 *
 * @return void
 */
	protected function _processExpectedExceptionAnnotations(): void {
		$name = $this->getName(false);
		if (!$name) {
			return;
		}
		try {
			$method = new \ReflectionMethod($this, $name);
		} catch (\ReflectionException $e) {
			return;
		}
		$doc = $method->getDocComment();
		if (!$doc) {
			return;
		}
		if (preg_match('/@expectedException\s+([^\s\*]+)/', $doc, $m)) {
			$this->expectException($m[1]);
			$this->_installErrorHandlerForExpectedException($m[1]);
		}
		if (preg_match('/@expectedExceptionMessage\s+([^\r\n\*]+)/', $doc, $m)) {
			$this->expectExceptionMessage(trim($m[1]));
		}
		if (preg_match('/@expectedExceptionMessageRegExp\s+([^\r\n\*]+)/', $doc, $m)) {
			$this->expectExceptionMessageMatches(trim($m[1]));
		}
		if (preg_match('/@expectedExceptionCode\s+([^\s\*]+)/', $doc, $m)) {
			$this->expectExceptionCode(is_numeric($m[1]) ? (int)$m[1] : $m[1]);
		}
	}

/**
 * When a test expects PHPUnit_Framework_Error{,_Warning,_Notice}, PHPUnit's
 * built-in error→exception conversion may not engage because CakePHP installs
 * its own error handler during bootstrap and PHPUnit's ErrorHandler::register()
 * refuses to install on top of an existing handler. Install a targeted handler
 * that throws the expected exception class so the test sees what it expects.
 *
 * @param string $exceptionClass The expected exception class from the docblock.
 * @return void
 */
	protected function _installErrorHandlerForExpectedException(string $exceptionClass): void {
		$expectsPhpError = (
			strpos($exceptionClass, 'PHPUnit_Framework_Error') === 0 ||
			strpos($exceptionClass, 'PHPUnit\\Framework\\Error\\') === 0
		);
		if (!$expectsPhpError) {
			return;
		}
		$this->_cakeTestExpectedErrorClass = $exceptionClass;
		set_error_handler(function ($severity, $message, $file, $line) use ($exceptionClass) {
			if (!($severity & error_reporting())) {
				return false;
			}
			$resolved = $exceptionClass;
			switch ($severity) {
				case E_NOTICE:
				case E_USER_NOTICE:
				case E_STRICT:
					$resolved = 'PHPUnit_Framework_Error_Notice';
					break;
				case E_WARNING:
				case E_USER_WARNING:
					$resolved = 'PHPUnit_Framework_Error_Warning';
					break;
				case E_DEPRECATED:
				case E_USER_DEPRECATED:
					return false;
				default:
					$resolved = 'PHPUnit_Framework_Error';
			}
			if (!class_exists($resolved, false)) {
				$resolved = $exceptionClass;
			}
			throw new $resolved($message, $severity, $file, $line);
		});
	}

/**
 * teardown any static object changes and restore them.
 *
 * @return void
 */
	public function tearDown(): void {
		if (isset($this->_cakeTestExpectedErrorClass)) {
			restore_error_handler();
			unset($this->_cakeTestExpectedErrorClass);
		}
		if (isset($this->_cakeTestServerBackup)) {
			foreach ($this->_cakeTestServerBackup as $key => $value) {
				if ($value === null) {
					unset($_SERVER[$key]);
				} else {
					$_SERVER[$key] = $value;
				}
			}
			unset($this->_cakeTestServerBackup);
		}
		parent::tearDown();
		App::build($this->_pathRestore, App::RESET);
		if (class_exists('ClassRegistry', false)) {
			ClassRegistry::flush();
		}
		if (!empty($this->_configure)) {
			Configure::clear();
			Configure::write($this->_configure);
		}
		if (isset($_GET['debug']) && $_GET['debug']) {
			ob_flush();
		}
		unset($this->_configure, $this->_pathRestore);
	}

/**
 * See CakeTestSuiteDispatcher::date()
 *
 * @param string $format format to be used.
 *
 * @return string
 */
	public static function date(string $format = 'Y-m-d H:i:s'): string {
		return CakeTestSuiteDispatcher::date($format);
	}

// @codingStandardsIgnoreStart PHPUnit overrides don't match CakePHP

/**
 * Announces the start of a test.
 *
 * @return void
 */
	protected function assertPreConditions(): void {
		parent::assertPreConditions();
		$this->startTest($this->getName());
	}

/**
 * Announces the end of a test.
 *
 * @return void
 */
	protected function assertPostConditions(): void {
		parent::assertPostConditions();
		$this->endTest($this->getName());
	}

// @codingStandardsIgnoreEnd

/**
 * Chooses which fixtures to load for a given test
 *
 * Each parameter is a model name that corresponds to a fixture, i.e. 'Post', 'Author', etc.
 *
 * @return void
 * @see CakeTestCase::$autoFixtures
 * @throws Exception when no fixture manager is available.
 */
	public function loadFixtures() {
		if (empty($this->fixtureManager)) {
			throw new Exception(__d('cake_dev', 'No fixture manager to load the test fixture'));
		}
		$args = func_get_args();
		foreach ($args as $class) {
			$this->fixtureManager->loadSingle($class, null, $this->dropTables);
		}
	}

/**
 * Assert text equality, ignoring differences in newlines.
 * Helpful for doing cross platform tests of blocks of text.
 *
 * @param string $expected The expected value.
 * @param string $result The actual value.
 * @param string $message The message to use for failure.
 *
 * @return void
 */
	public function assertTextNotEquals(string $expected, string $result, string $message = ''): void {
		$expected = str_replace(array("\r\n", "\r"), "\n", $expected);
		$result = str_replace(array("\r\n", "\r"), "\n", $result);
		$this->assertNotEquals($expected, $result, $message);
	}

/**
 * Assert text equality, ignoring differences in newlines.
 * Helpful for doing cross platform tests of blocks of text.
 *
 * @param string $expected The expected value.
 * @param string $result The actual value.
 * @param string $message message The message to use for failure.
 *
 * @return void
 */
	public function assertTextEquals(string $expected, string $result, string $message = ''): void {
		$expected = str_replace(array("\r\n", "\r"), "\n", $expected);
		$result = str_replace(array("\r\n", "\r"), "\n", $result);
		$this->assertEquals($expected, $result, $message);
	}

/**
 * Asserts that a string starts with a given prefix, ignoring differences in newlines.
 * Helpful for doing cross platform tests of blocks of text.
 *
 * @param string $prefix The prefix to check for.
 * @param string $string The string to search in.
 * @param string $message The message to use for failure.
 *
 * @return void
 */
	public function assertTextStartsWith(string $prefix, string $string, string $message = ''): void {
		$prefix = str_replace(array("\r\n", "\r"), "\n", $prefix);
		$string = str_replace(array("\r\n", "\r"), "\n", $string);
		$this->assertStringStartsWith($prefix, $string, $message);
	}

/**
 * Asserts that a string starts not with a given prefix, ignoring differences in newlines.
 * Helpful for doing cross platform tests of blocks of text.
 *
 * @param string $prefix The prefix to not find.
 * @param string $string The string to search.
 * @param string $message The message to use for failure.
 * @return bool
 */
	public function assertTextStartsNotWith($prefix, $string, $message = '') {
		$prefix = str_replace(array("\r\n", "\r"), "\n", $prefix);
		$string = str_replace(array("\r\n", "\r"), "\n", $string);
		return $this->assertStringStartsNotWith($prefix, $string, $message);
	}

/**
 * Asserts that a string ends with a given prefix, ignoring differences in newlines.
 * Helpful for doing cross platform tests of blocks of text.
 *
 * @param string $suffix The suffix to find.
 * @param string $string The string to search.
 * @param string $message The message to use for failure.
 * @return bool
 */
	public function assertTextEndsWith($suffix, $string, $message = '') {
		$suffix = str_replace(array("\r\n", "\r"), "\n", $suffix);
		$string = str_replace(array("\r\n", "\r"), "\n", $string);
		return $this->assertStringEndsWith($suffix, $string, $message);
	}

/**
 * Asserts that a string ends not with a given prefix, ignoring differences in newlines.
 * Helpful for doing cross platform tests of blocks of text.
 *
 * @param string $suffix The suffix to not find.
 * @param string $string The string to search.
 * @param string $message The message to use for failure.
 * @return bool
 */
	public function assertTextEndsNotWith($suffix, $string, $message = '') {
		$suffix = str_replace(array("\r\n", "\r"), "\n", $suffix);
		$string = str_replace(array("\r\n", "\r"), "\n", $string);
		return $this->assertStringEndsNotWith($suffix, $string, $message);
	}

/**
 * Assert that a string contains another string, ignoring differences in newlines.
 * Helpful for doing cross platform tests of blocks of text.
 *
 * @param string $needle The string to search for.
 * @param string $haystack The string to search through.
 * @param string $message The message to display on failure.
 * @param bool $ignoreCase Whether or not the search should be case-sensitive.
 * @return void
 */
	public function assertTextContains($needle, $haystack, $message = '', $ignoreCase = false) {
		$needle = str_replace(array("\r\n", "\r"), "\n", $needle);
		$haystack = str_replace(array("\r\n", "\r"), "\n", $haystack);
		if ($ignoreCase) {
			$this->assertTrue(strcasecmp($haystack, $needle) == 0, $message);
		} else {
			$this->assertStringContainsString($needle, $haystack, $message);
		}
	}

/**
 * Assert that a text doesn't contain another text, ignoring differences in newlines.
 * Helpful for doing cross platform tests of blocks of text.
 *
 * @param string $needle The string to search for.
 * @param string $haystack The string to search through.
 * @param string $message The message to display on failure.
 * @param bool $ignoreCase Whether or not the search should be case-sensitive.
 * @return bool
 */
	public function assertTextNotContains($needle, $haystack, $message = '', $ignoreCase = false) {
		$needle = str_replace(array("\r\n", "\r"), "\n", $needle);
		$haystack = str_replace(array("\r\n", "\r"), "\n", $haystack);
		return $this->assertNotContains($needle, $haystack, $message, $ignoreCase);
	}

/**
 * Takes an array $expected and generates a regex from it to match the provided $string.
 * Samples for $expected:
 *
 * Checks for an input tag with a name attribute (contains any non-empty value) and an id
 * attribute that contains 'my-input':
 *
 * ```
 * array('input' => array('name', 'id' => 'my-input'))
 * ```
 *
 * Checks for two p elements with some text in them:
 *
 * ```
 * array(
 *   array('p' => true),
 *   'textA',
 *   '/p',
 *   array('p' => true),
 *   'textB',
 *   '/p'
 * )
 * ```
 *
 * You can also specify a pattern expression as part of the attribute values, or the tag
 * being defined, if you prepend the value with preg: and enclose it with slashes, like so:
 *
 * ```
 * array(
 *   array('input' => array('name', 'id' => 'preg:/FieldName\d+/')),
 *   'preg:/My\s+field/'
 * )
 * ```
 *
 * Important: This function is very forgiving about whitespace and also accepts any
 * permutation of attribute order. It will also allow whitespace between specified tags.
 *
 * @param string $string An HTML/XHTML/XML string
 * @param array $expected An array, see above
 * @param string $fullDebug Whether or not more verbose output should be used.
 * @return bool
 */
	public function assertTags($string, $expected, $fullDebug = false) {
		$regex = array();
		$normalized = array();
		foreach ((array)$expected as $key => $val) {
			if (!is_numeric($key)) {
				$normalized[] = array($key => $val);
			} else {
				$normalized[] = $val;
			}
		}
		$i = 0;
		foreach ($normalized as $tags) {
			if (!is_array($tags)) {
				$tags = (string)$tags;
			}
			$i++;
			if (is_string($tags) && $tags[0] === '<') {
				$tags = array(substr($tags, 1) => array());
			} elseif (is_string($tags)) {
				$tagsTrimmed = preg_replace('/\s+/m', '', $tags);

				if (preg_match('/^\*?\//', $tags, $match) && $tagsTrimmed !== '//') {
					$prefix = array(null, null);

					if ($match[0] === '*/') {
						$prefix = array('Anything, ', '.*?');
					}
					$regex[] = array(
						sprintf('%sClose %s tag', $prefix[0], substr($tags, strlen($match[0]))),
						sprintf('%s<[\s]*\/[\s]*%s[\s]*>[\n\r]*', $prefix[1], substr($tags, strlen($match[0]))),
						$i,
					);
					continue;
				}
				if (!empty($tags) && preg_match('/^preg\:\/(.+)\/$/i', $tags, $matches)) {
					$tags = $matches[1];
					$type = 'Regex matches';
				} else {
					$tags = preg_quote($tags, '/');
					$type = 'Text equals';
				}
				$regex[] = array(
					sprintf('%s "%s"', $type, $tags),
					$tags,
					$i,
				);
				continue;
			}
			foreach ($tags as $tag => $attributes) {
				$regex[] = array(
					sprintf('Open %s tag', $tag),
					sprintf('[\s]*<%s', preg_quote($tag, '/')),
					$i,
				);
				if ($attributes === true) {
					$attributes = array();
				}
				$attrs = array();
				$explanations = array();
				$i = 1;
				foreach ($attributes as $attr => $val) {
					if (is_numeric($attr) && preg_match('/^preg\:\/(.+)\/$/i', $val, $matches)) {
						$attrs[] = $matches[1];
						$explanations[] = sprintf('Regex "%s" matches', $matches[1]);
						continue;
					} else {
						$quotes = '["\']';
						if (is_numeric($attr)) {
							$attr = $val;
							$val = '.+?';
							$explanations[] = sprintf('Attribute "%s" present', $attr);
						} elseif (!empty($val) && preg_match('/^preg\:\/(.+)\/$/i', $val, $matches)) {
							$val = str_replace(
								array('.*', '.+'),
								array('.*?', '.+?'),
								$matches[1]
							);
							$quotes = $val !== $matches[1] ? '["\']' : '["\']?';

							$explanations[] = sprintf('Attribute "%s" matches "%s"', $attr, $val);
						} else {
							$explanations[] = sprintf('Attribute "%s" == "%s"', $attr, $val);
							$val = preg_quote($val, '/');
						}
						$attrs[] = '[\s]+' . preg_quote($attr, '/') . '=' . $quotes . $val . $quotes;
					}
					$i++;
				}
				if ($attrs) {
					$regex[] = array(
						'explains' => $explanations,
						'attrs' => $attrs,
					);
				}
				$regex[] = array(
					sprintf('End %s tag', $tag),
					'[\s]*\/?[\s]*>[\n\r]*',
					$i,
				);
			}
		}
		foreach ($regex as $i => $assertion) {
			$matches = false;
			if (isset($assertion['attrs'])) {
				$string = $this->_assertAttributes($assertion, $string);
				continue;
			}

			[$description, $expressions, $itemNum] = $assertion;
			foreach ((array)$expressions as $expression) {
				if (preg_match(sprintf('/^%s/s', $expression), $string, $match)) {
					$matches = true;
					$string = substr($string, strlen($match[0]));
					break;
				}
			}
			if (!$matches) {
				$this->assertTrue(false, sprintf('Item #%d / regex #%d failed: %s', $itemNum, $i, $description));
				if ($fullDebug) {
					debug($string, true);
					debug($regex, true);
				}
				return false;
			}
		}

		$this->assertTrue(true, '%s');
		return true;
	}

/**
 * Check the attributes as part of an assertTags() check.
 *
 * @param array $assertions Assertions to run.
 * @param string $string The HTML string to check.
 * @return void
 */
	protected function _assertAttributes($assertions, $string) {
		$asserts = $assertions['attrs'];
		$explains = $assertions['explains'];
		$len = count($asserts);
		do {
			$matches = false;
			foreach ($asserts as $j => $assert) {
				if (preg_match(sprintf('/^%s/s', $assert), $string, $match)) {
					$matches = true;
					$string = substr($string, strlen($match[0]));
					array_splice($asserts, $j, 1);
					array_splice($explains, $j, 1);
					break;
				}
			}
			if ($matches === false) {
				$this->assertTrue(false, 'Attribute did not match. Was expecting ' . $explains[$j]);
			}
			$len = count($asserts);
		} while ($len > 0);
		return $string;
	}

// @codingStandardsIgnoreStart

/**
 * Compatibility wrapper function for assertEquals
 *
 * @param mixed $result
 * @param mixed $expected
 * @param string $message the text to display if the assertion is not correct
 * @deprecated 3.0.0 This is a compatibility wrapper for 1.x. It will be removed in 3.0
 * @return void
 */
	protected static function assertEqual($result, $expected, $message = '') {
		return static::assertEquals($expected, $result, $message);
	}

/**
 * Compatibility wrapper function for assertNotEquals
 *
 * @param mixed $result
 * @param mixed $expected
 * @param string $message the text to display if the assertion is not correct
 * @deprecated 3.0.0 This is a compatibility wrapper for 1.x. It will be removed in 3.0
 * @return void
 */
	protected static function assertNotEqual($result, $expected, $message = '') {
		return static::assertNotEquals($expected, $result, $message);
	}

/**
 * Compatibility wrapper function for assertRegexp
 *
 * @param mixed $pattern a regular expression
 * @param string $string the text to be matched
 * @param string $message the text to display if the assertion is not correct
 * @deprecated 3.0.0 This is a compatibility wrapper for 1.x. It will be removed in 3.0
 * @return void
 */
	protected static function assertPattern($pattern, $string, $message = '') {
		return static::assertRegExp($pattern, $string, $message);
	}

/**
 * Compatibility wrapper function for assertEquals
 *
 * @param mixed $actual
 * @param mixed $expected
 * @param string $message the text to display if the assertion is not correct
 * @deprecated 3.0.0 This is a compatibility wrapper for 1.x. It will be removed in 3.0
 * @return void
 */
	protected static function assertIdentical($actual, $expected, $message = '') {
		return static::assertSame($expected, $actual, $message);
	}

/**
 * Compatibility wrapper function for assertNotEquals
 *
 * @param mixed $actual
 * @param mixed $expected
 * @param string $message the text to display if the assertion is not correct
 * @deprecated 3.0.0 This is a compatibility wrapper for 1.x. It will be removed in 3.0
 * @return void
 */
	protected static function assertNotIdentical($actual, $expected, $message = '') {
		return static::assertNotSame($expected, $actual, $message);
	}

/**
 * Compatibility wrapper function for assertNotRegExp
 *
 * @param mixed $pattern a regular expression
 * @param string $string the text to be matched
 * @param string $message the text to display if the assertion is not correct
 * @deprecated 3.0.0 This is a compatibility wrapper for 1.x. It will be removed in 3.0
 * @return void
 */
	protected static function assertNoPattern($pattern, $string, $message = '') {
		return static::assertNotRegExp($pattern, $string, $message);
	}

/**
 * assert no errors
 *
 * @deprecated 3.0.0 This is a compatibility wrapper for 1.x. It will be removed in 3.0
 * @return void
 */
	protected function assertNoErrors() {
	}

/**
 * Compatibility wrapper function for assertSame
 *
 * @param mixed $first
 * @param mixed $second
 * @param string $message the text to display if the assertion is not correct
 * @deprecated 3.0.0 This is a compatibility wrapper for 1.x. It will be removed in 3.0
 * @return void
 */
	protected static function assertReference(&$first, &$second, $message = '') {
		return static::assertSame($first, $second, $message);
	}

/**
 * Compatibility wrapper for assertIsA
 *
 * @param string $object
 * @param string $type
 * @param string $message
 * @deprecated 3.0.0 This is a compatibility wrapper for 1.x. It will be removed in 3.0
 * @return void
 */
	protected static function assertIsA($object, $type, $message = '') {
		return static::assertInstanceOf($type, $object, $message);
	}

/**
 * Compatibility function to test if value is between an acceptable range
 *
 * @param mixed $result
 * @param mixed $expected
 * @param mixed $margin the rage of acceptation
 * @param string $message the text to display if the assertion is not correct
 * @return void
 */
	protected static function assertWithinMargin($result, $expected, $margin, $message = '') {
		$upper = $result + $margin;
		$lower = $result - $margin;
		return static::assertTrue((($expected <= $upper) && ($expected >= $lower)), $message);
	}

/**
 * Compatibility function for skipping.
 *
 * @param bool $condition Condition to trigger skipping
 * @param string $message Message for skip
 * @return bool
 */
	protected function skipUnless($condition, $message = '') {
		if (!$condition) {
			$this->markTestSkipped($message);
		}
		return $condition;
	}
	// @codingStandardsIgnoreEnd

/**
 * Returns a mock object for the specified class.
 *
 * @param string $originalClassName The class name of the object to be mocked.
 * @param array $methods By default, all methods of the given class are replaced
 *   with a test double that just returns NULL unless a return value is configured
 *   using will($this->returnValue()), for instance.
 *   When the second (optional) parameter is provided, only the methods whose names
 *   are in the array are replaced with a configurable test double. The behavior
 *   of the other methods is not changed. Providing NULL as the parameter means
 *   that no methods will be replaced.
 * @param array $arguments The third (optional) parameter may hold a parameter
 *   array that is passed to the original class' constructor (which is not replaced
 *   with a dummy implementation by default).
 * @param string $mockClassName The fourth (optional) parameter can be used to
 *   specify a class name for the generated test double class.
 * @param bool $callOriginalConstructor The fifth (optional) parameter can be
 *   used to disable the call to the original class' constructor.
 * @param bool $callOriginalClone The sixth (optional) parameter can be used
 *   to disable the call to the original class' clone constructor.
 * @param bool $callAutoload The seventh (optional) parameter can be used to
 *   disable __autoload() during the generation of the test double class.
 * @return object
 * @deprecated Use `getMockBuilder()` or `createMock()` in new unit tests.
 * @see https://phpunit.de/manual/current/en/test-doubles.html
 */
	protected function _buildMock(
		$originalClassName,
		$methods = array(),
		array $arguments = array(),
		$mockClassName = '',
		$callOriginalConstructor = true,
		$callOriginalClone = true,
		$callAutoload = true
	) {
		$MockBuilder = $this->getMockBuilder($originalClassName);
		if (!empty($methods)) {
			$MockBuilder = $MockBuilder->setMethods($methods);
		}
		if (!empty($arguments)) {
			$MockBuilder = $MockBuilder->setConstructorArgs($arguments);
		}
		if ($mockClassName != '') {
			$MockBuilder = $MockBuilder->setMockClassName($mockClassName);
		}
		if ($callOriginalConstructor !== true) {
			$MockBuilder = $MockBuilder->disableOriginalConstructor();
		}
		if ($callOriginalClone !== true) {
			$MockBuilder = $MockBuilder->disableOriginalClone();
		}
		if ($callAutoload !== true) {
			$MockBuilder = $MockBuilder->disableAutoload();
		}
		return $MockBuilder->getMock();
	}

/**
 * Returns a mock object for the specified class.
 *
 * @param string $originalClassName The class name of the object to be mocked.
 * @param array $methods By default, all methods of the given class are replaced
 *   with a test double that just returns NULL unless a return value is configured
 *   using will($this->returnValue()), for instance.
 *   When the second (optional) parameter is provided, only the methods whose names
 *   are in the array are replaced with a configurable test double. The behavior
 *   of the other methods is not changed. Providing NULL as the parameter means
 *   that no methods will be replaced.
 * @param array $arguments The third (optional) parameter may hold a parameter
 *   array that is passed to the original class' constructor (which is not replaced
 *   with a dummy implementation by default).
 * @param string $mockClassName The fourth (optional) parameter can be used to
 *   specify a class name for the generated test double class.
 * @param bool $callOriginalConstructor The fifth (optional) parameter can be
 *   used to disable the call to the original class' constructor.
 * @param bool $callOriginalClone The sixth (optional) parameter can be used
 *   to disable the call to the original class' clone constructor.
 * @param bool $callAutoload The seventh (optional) parameter can be used to
 *   disable __autoload() during the generation of the test double class.
 * @param bool $cloneArguments Not supported.
 * @param bool $callOriginalMethods Not supported.
 * @param string $proxyTarget Not supported.
 * @return object
 * @throws InvalidArgumentException When not supported parameters are set.
 * @deprecated Use `getMockBuilder()` or `createMock()` in new unit tests.
 * @see https://phpunit.de/manual/current/en/test-doubles.html
 */
	public function getMock(
		$originalClassName,
		$methods = array(),
		array $arguments = array(),
		$mockClassName = '',
		$callOriginalConstructor = true,
		$callOriginalClone = true,
		$callAutoload = true,
		$cloneArguments = false,
		$callOriginalMethods = false,
		$proxyTarget = null
	) {
		$phpUnitVersion = \PHPUnit\Runner\Version::id();
		if (version_compare($phpUnitVersion, '5.7.0', '<')) {
			return parent::getMock($originalClassName, $methods, $arguments,
					$mockClassName, $callOriginalConstructor, $callOriginalClone,
					$callAutoload, $cloneArguments, $callOriginalMethods, $proxyTarget);
		}
		if ($cloneArguments) {
			throw new InvalidArgumentException('$cloneArguments parameter is not supported');
		}
		if ($callOriginalMethods) {
			throw new InvalidArgumentException('$callOriginalMethods parameter is not supported');
		}
		if ($proxyTarget !== null) {
			throw new InvalidArgumentException('$proxyTarget parameter is not supported');
		}
		return $this->_buildMock(
			$originalClassName,
			$methods,
			$arguments,
			$mockClassName,
			$callOriginalConstructor,
			$callOriginalClone,
			$callAutoload
		);
	}

/**
 * Mock a model, maintain fixtures and table association
 *
 * @param string $model The model to get a mock for.
 * @param mixed $methods The list of methods to mock
 * @param array $config The config data for the mock's constructor.
 * @throws MissingModelException
 * @return Model
 */
	public function getMockForModel($model, $methods = array(), $config = array()) {
		$defaults = ClassRegistry::config('Model');
		unset($defaults['ds']);

		[$plugin, $name] = pluginSplit($model, true);
		App::uses($name, $plugin . 'Model');

		$config = array_merge($defaults, (array)$config, array('name' => $name));

		if (!class_exists($name)) {
			throw new MissingModelException(array($model));
		}
		$mock = $this->getMock($name, $methods, array($config));

		$availableDs = array_keys(ConnectionManager::enumConnectionObjects());

		if ($mock->useDbConfig !== 'test' && in_array('test_' . $mock->useDbConfig, $availableDs)) {
			$mock->useDbConfig = 'test_' . $mock->useDbConfig;
			$mock->setDataSource($mock->useDbConfig);
		} else {
			$mock->useDbConfig = 'test';
			$mock->setDataSource('test');
		}

		ClassRegistry::removeObject($name);
		ClassRegistry::addObject($name, $mock);
		return $mock;
	}
}
