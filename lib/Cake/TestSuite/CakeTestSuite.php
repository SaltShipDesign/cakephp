<?php

use PHPUnit\Framework\TestSuite;

App::uses('CakeTestCase', 'TestSuite');
App::uses('Folder', 'Utility');

class CakeTestSuite extends TestSuite {

/**
 * Add test directory
 *
 * @param string $directory Path to folder
 * @return void
 */
	public function addTestDirectory(string $directory = '.'): void {
		$Folder = new Folder($directory);
		[, $files] = $Folder->read(true, true, true);

		foreach ($files as $file) {
			if (substr($file, -4) === '.php') {
				$this->addTestFile($file);
			}
		}
	}

/**
 * Add test directory
 *
 * @param string $directory Path to folder
 * @return void
 */
	public function addTestDirectoryRecursive(string $directory = '.'): void {
		$Folder = new Folder($directory);
		$files = $Folder->tree(null, true, 'files');

		foreach ($files as $file) {
			if (substr($file, -4) === '.php') {
				$this->addTestFile($file);
			}
		}
	}
}
