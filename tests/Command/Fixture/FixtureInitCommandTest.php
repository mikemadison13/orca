<?php

namespace Acquia\Orca\Tests\Command\Fixture;

use Acquia\Orca\Command\Fixture\FixtureInitCommand;
use Acquia\Orca\Command\StatusCodes;
use Acquia\Orca\Exception\OrcaException;
use Acquia\Orca\Fixture\PackageManager;
use Acquia\Orca\Fixture\FixtureRemover;
use Acquia\Orca\Fixture\Fixture;
use Acquia\Orca\Fixture\FixtureCreator;
use Acquia\Orca\Fixture\SutPreconditionsTester;
use Acquia\Orca\Tests\Command\CommandTestBase;
use Acquia\Orca\Enum\DrupalCoreVersion;
use Acquia\Orca\Utility\DrupalCoreVersionFinder;
use Composer\Semver\VersionParser;
use Prophecy\Argument;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @property \Prophecy\Prophecy\ObjectProphecy|\Acquia\Orca\Utility\DrupalCoreVersionFinder $drupalCoreVersionFinder
 * @property \Prophecy\Prophecy\ObjectProphecy|\Acquia\Orca\Fixture\Fixture $fixture
 * @property \Prophecy\Prophecy\ObjectProphecy|\Acquia\Orca\Fixture\FixtureCreator $fixtureCreator
 * @property \Prophecy\Prophecy\ObjectProphecy|\Acquia\Orca\Fixture\FixtureRemover $fixtureRemover
 * @property \Prophecy\Prophecy\ObjectProphecy|\Acquia\Orca\Fixture\PackageManager $packageManager
 * @property \Prophecy\Prophecy\ObjectProphecy|\Acquia\Orca\Fixture\SutPreconditionsTester sutPreconditionsTester
 * @property \Prophecy\Prophecy\ObjectProphecy|\Composer\Semver\VersionParser $versionParser
 */
class FixtureInitCommandTest extends CommandTestBase {

  private const CORE_VALUE_LITERAL_PREVIOUS_RELEASE = '8.5.14.0';

  private const CORE_VALUE_LITERAL_PREVIOUS_DEV = '8.5.x-dev';

  private const CORE_VALUE_LITERAL_CURRENT_RECOMMENDED = '8.6.14.0';

  private const CORE_VALUE_LITERAL_CURRENT_DEV = '8.6.x-dev';

  private const CORE_VALUE_LITERAL_NEXT_RELEASE = '8.7.0.0-beta2';

  private const CORE_VALUE_LITERAL_NEXT_DEV = '8.7.x-dev';

  protected function setUp() {
    $this->drupalCoreVersionFinder = $this->prophesize(DrupalCoreVersionFinder::class);
    $this->drupalCoreVersionFinder
      ->getPreviousMinorRelease()
      ->willReturn(self::CORE_VALUE_LITERAL_PREVIOUS_RELEASE);
    $this->drupalCoreVersionFinder
      ->getCurrentRecommendedRelease()
      ->willReturn(self::CORE_VALUE_LITERAL_CURRENT_RECOMMENDED);
    $this->drupalCoreVersionFinder
      ->getCurrentDevVersion()
      ->willReturn(self::CORE_VALUE_LITERAL_CURRENT_DEV);
    $this->drupalCoreVersionFinder
      ->getNextRelease()
      ->willReturn(self::CORE_VALUE_LITERAL_NEXT_RELEASE);
    $this->fixtureCreator = $this->prophesize(FixtureCreator::class);
    $this->fixtureRemover = $this->prophesize(FixtureRemover::class);
    $this->fixture = $this->prophesize(Fixture::class);
    $this->fixture->exists()
      ->willReturn(FALSE);
    $this->fixture->getPath()
      ->willReturn(self::FIXTURE_ROOT);
    $this->packageManager = $this->prophesize(PackageManager::class);
    $this->packageManager
      ->exists(Argument::any())
      ->wilLReturn(TRUE);
    $this->sutPreconditionsTester = $this->prophesize(SutPreconditionsTester::class);
    $this->versionParser = $this->prophesize(VersionParser::class);
  }

  /**
   * @dataProvider providerCommand
   */
  public function testCommand($fixture_exists, $args, $methods_called, $drupal_core_version, $status_code, $display) {
    $this->packageManager
      ->exists(@$args['--sut'])
      ->shouldBeCalledTimes((int) in_array('PackageManager::exists', $methods_called))
      ->willReturn(@$args['--sut'] === self::VALID_PACKAGE);
    $this->fixture
      ->exists()
      ->shouldBeCalledTimes((int) in_array('Fixture::exists', $methods_called))
      ->willReturn($fixture_exists);
    $this->fixtureRemover
      ->remove()
      ->shouldBeCalledTimes((int) in_array('remove', $methods_called));
    $this->drupalCoreVersionFinder
      ->getPreviousMinorRelease()
      ->shouldBeCalledTimes((int) in_array('getPreviousMinorVersion', $methods_called))
      ->willReturn($drupal_core_version);
    $this->drupalCoreVersionFinder
      ->getCurrentRecommendedRelease()
      ->shouldBeCalledTimes((int) in_array('getCurrentRecommendedVersion', $methods_called))
      ->willReturn(self::CORE_VALUE_LITERAL_CURRENT_RECOMMENDED);
    $this->drupalCoreVersionFinder
      ->getCurrentDevVersion()
      ->shouldBeCalledTimes((int) in_array('getCurrentDevVersion', $methods_called))
      ->willReturn($drupal_core_version);
    $this->drupalCoreVersionFinder
      ->getNextRelease()
      ->shouldBeCalledTimes((int) in_array('getLatestPreReleaseVersion', $methods_called))
      ->willReturn($drupal_core_version);
    $this->fixtureCreator
      ->setSut(@$args['--sut'])
      ->shouldBeCalledTimes((int) in_array('setSut', $methods_called));
    $this->fixtureCreator
      ->setSutOnly(TRUE)
      ->shouldBeCalledTimes((int) in_array('setSutOnly', $methods_called));
    $this->fixtureCreator
      ->setDev(TRUE)
      ->shouldBeCalledTimes((int) in_array('setDev', $methods_called));
    $this->fixtureCreator
      ->setCoreVersion($drupal_core_version ?: self::CORE_VALUE_LITERAL_CURRENT_RECOMMENDED)
      ->shouldBeCalledTimes((int) in_array('setCoreVersion', $methods_called));
    $this->fixtureCreator
      ->setSqlite(FALSE)
      ->shouldBeCalledTimes((int) in_array('setSqlite', $methods_called));
    $this->fixtureCreator
      ->setProfile((@$args['--profile']) ?: 'minimal')
      ->shouldBeCalledTimes((int) in_array('setProfile', $methods_called));
    $this->fixtureCreator
      ->setInstallSite(FALSE)
      ->shouldBeCalledTimes((int) in_array('setInstallSite', $methods_called));
    $this->fixtureCreator
      ->create()
      ->shouldBeCalledTimes((int) in_array('create', $methods_called));
    $tester = $this->createCommandTester();

    $this->executeCommand($tester, FixtureInitCommand::getDefaultName(), $args);

    $this->assertEquals($display, $tester->getDisplay(), 'Displayed correct output.');
    $this->assertEquals($status_code, $tester->getStatusCode(), 'Returned correct status code.');
  }

  public function providerCommand() {
    return [
      [TRUE, [], ['Fixture::exists'], NULL, StatusCodes::ERROR, sprintf("Error: Fixture already exists at %s.\nHint: Use the \"--force\" option to remove it and proceed.\n", self::FIXTURE_ROOT)],
      [TRUE, ['-f' => TRUE], ['Fixture::exists', 'remove', 'create'], NULL, StatusCodes::OK, ''],
      [FALSE, [], ['Fixture::exists', 'create'], NULL, StatusCodes::OK, ''],
      [FALSE, ['--sut' => self::INVALID_PACKAGE], ['PackageManager::exists'], NULL, StatusCodes::ERROR, sprintf("Error: Invalid value for \"--sut\" option: \"%s\".\n", self::INVALID_PACKAGE)],
      [FALSE, ['--sut' => self::VALID_PACKAGE], ['PackageManager::exists', 'Fixture::exists', 'create', 'setSut'], NULL, StatusCodes::OK, ''],
      [FALSE, ['--sut' => self::VALID_PACKAGE, '--sut-only' => TRUE], ['PackageManager::exists', 'Fixture::exists', 'create', 'setSut', 'setSutOnly'], NULL, StatusCodes::OK, ''],
      [FALSE, ['--dev' => TRUE], ['Fixture::exists', 'setDev', 'getCurrentDevVersion', 'setCoreVersion', 'create'], self::CORE_VALUE_LITERAL_CURRENT_DEV, StatusCodes::OK, ''],
      [FALSE, ['--no-site-install' => TRUE], ['Fixture::exists', 'setInstallSite', 'create'], NULL, StatusCodes::OK, ''],
      [FALSE, ['--no-sqlite' => TRUE], ['Fixture::exists', 'setSqlite', 'create'], NULL, StatusCodes::OK, ''],
      [FALSE, ['--profile' => 'lightning'], ['Fixture::exists', 'setProfile', 'create'], NULL, StatusCodes::OK, ''],
      [FALSE, ['--sut-only' => TRUE], [], NULL, StatusCodes::ERROR, "Error: Cannot create a SUT-only fixture without a SUT.\nHint: Use the \"--sut\" option to specify the SUT.\n"],
    ];
  }

  public function testNoOptions() {
    $this->versionParser = new VersionParser();
    $tester = $this->createCommandTester();

    $this->executeCommand($tester, FixtureInitCommand::getDefaultName());

    $this->assertEquals('', $tester->getDisplay(), 'Displayed correct output.');
    $this->assertEquals(StatusCodes::OK, $tester->getStatusCode(), 'Returned correct status code.');
  }

  public function testBareOption() {
    $this->fixtureCreator
      ->setBare(TRUE)
      ->shouldBeCalledTimes(1);
    $this->fixtureCreator
      ->create()
      ->shouldBeCalledTimes(1);
    $tester = $this->createCommandTester();

    $this->executeCommand($tester, FixtureInitCommand::getDefaultName(), ['--bare' => TRUE]);

    $this->assertEquals('', $tester->getDisplay(), 'Displayed correct output.');
    $this->assertEquals(StatusCodes::OK, $tester->getStatusCode(), 'Returned correct status code.');
  }

  /**
   * @dataProvider providerBareOptionInvalidProvider
   */
  public function testBareOptionInvalid($options) {
    $this->fixtureCreator
      ->create()
      ->shouldNotBeCalled();
    $tester = $this->createCommandTester();

    $this->executeCommand($tester, FixtureInitCommand::getDefaultName(), $options);

    $this->assertEquals("Error: Cannot create a bare fixture with a SUT.\n", $tester->getDisplay(), 'Displayed correct output.');
    $this->assertEquals(StatusCodes::ERROR, $tester->getStatusCode(), 'Returned correct status code.');
  }

  public function providerBareOptionInvalidProvider() {
    return [
      [['--bare' => TRUE, '--sut' => 'drupal/example']],
      [['--bare' => TRUE, '--sut' => 'drupal/example', '--sut-only' => TRUE]],
    ];
  }

  /**
   * @dataProvider providerCoreOption
   */
  public function testCoreOption($value, $options, $set_version) {
    $this->drupalCoreVersionFinder
      ->getPreviousMinorRelease()
      ->shouldBeCalledTimes((int) ($value === DrupalCoreVersion::PREVIOUS_RELEASE()->getValue()))
      ->willReturn(self::CORE_VALUE_LITERAL_PREVIOUS_RELEASE);
    $this->drupalCoreVersionFinder
      ->getPreviousDevVersion()
      ->shouldBeCalledTimes((int) ($value === DrupalCoreVersion::PREVIOUS_DEV()->getValue()))
      ->willReturn(self::CORE_VALUE_LITERAL_PREVIOUS_DEV);
    $this->drupalCoreVersionFinder
      ->getCurrentRecommendedRelease()
      ->shouldBeCalledTimes((int) ($value === DrupalCoreVersion::CURRENT_RECOMMENDED()->getValue()))
      ->willReturn(self::CORE_VALUE_LITERAL_CURRENT_RECOMMENDED);
    $this->drupalCoreVersionFinder
      ->getCurrentDevVersion()
      ->shouldBeCalledTimes((int) ($value === DrupalCoreVersion::CURRENT_DEV()->getValue()))
      ->willReturn(self::CORE_VALUE_LITERAL_CURRENT_DEV);
    $this->drupalCoreVersionFinder
      ->getNextRelease()
      ->shouldBeCalledTimes((int) ($value === DrupalCoreVersion::NEXT_RELEASE()->getValue()))
      ->willReturn(self::CORE_VALUE_LITERAL_NEXT_RELEASE);
    $this->drupalCoreVersionFinder
      ->getNextDevVersion()
      ->shouldBeCalledTimes((int) ($value === DrupalCoreVersion::NEXT_DEV()->getValue()))
      ->willReturn(self::CORE_VALUE_LITERAL_NEXT_DEV);
    $this->fixtureCreator
      ->setDev(TRUE)
      ->shouldBeCalledTimes((int) isset($options['--dev']));
    $this->fixtureCreator
      ->setCoreVersion($set_version)
      ->shouldBeCalledTimes(1);
    $this->fixtureCreator
      ->create()
      ->shouldBeCalledTimes(1);
    $tester = $this->createCommandTester();

    $this->executeCommand($tester, FixtureInitCommand::getDefaultName(), $options);

    $this->assertEquals('', $tester->getDisplay(), 'Displayed correct output.');
    $this->assertEquals(StatusCodes::OK, $tester->getStatusCode(), 'Returned correct status code.');
  }

  public function providerCoreOption() {
    return [
      [DrupalCoreVersion::PREVIOUS_RELEASE()->getValue(), ['--core' => DrupalCoreVersion::PREVIOUS_RELEASE()->getValue()], self::CORE_VALUE_LITERAL_PREVIOUS_RELEASE],
      [DrupalCoreVersion::PREVIOUS_DEV()->getValue(), ['--core' => DrupalCoreVersion::PREVIOUS_DEV()->getValue()], self::CORE_VALUE_LITERAL_PREVIOUS_DEV],
      [DrupalCoreVersion::CURRENT_RECOMMENDED()->getValue(), ['--core' => DrupalCoreVersion::CURRENT_RECOMMENDED()->getValue()], self::CORE_VALUE_LITERAL_CURRENT_RECOMMENDED],
      [DrupalCoreVersion::CURRENT_DEV()->getValue(), ['--core' => DrupalCoreVersion::CURRENT_DEV()->getValue()], self::CORE_VALUE_LITERAL_CURRENT_DEV],
      [DrupalCoreVersion::CURRENT_DEV()->getValue(), ['--dev' => TRUE], self::CORE_VALUE_LITERAL_CURRENT_DEV],
      [DrupalCoreVersion::NEXT_RELEASE()->getValue(), ['--core' => DrupalCoreVersion::NEXT_RELEASE()->getValue()], self::CORE_VALUE_LITERAL_NEXT_RELEASE],
      [DrupalCoreVersion::NEXT_DEV()->getValue(), ['--core' => DrupalCoreVersion::NEXT_DEV()->getValue()], self::CORE_VALUE_LITERAL_NEXT_DEV],
      [self::CORE_VALUE_LITERAL_PREVIOUS_RELEASE, ['--core' => self::CORE_VALUE_LITERAL_PREVIOUS_RELEASE], self::CORE_VALUE_LITERAL_PREVIOUS_RELEASE],
      [self::CORE_VALUE_LITERAL_CURRENT_RECOMMENDED, ['--core' => self::CORE_VALUE_LITERAL_CURRENT_RECOMMENDED], self::CORE_VALUE_LITERAL_CURRENT_RECOMMENDED],
      [self::CORE_VALUE_LITERAL_CURRENT_DEV, ['--core' => self::CORE_VALUE_LITERAL_CURRENT_DEV], self::CORE_VALUE_LITERAL_CURRENT_DEV],
      [self::CORE_VALUE_LITERAL_NEXT_RELEASE, ['--core' => self::CORE_VALUE_LITERAL_NEXT_RELEASE], self::CORE_VALUE_LITERAL_NEXT_RELEASE],
    ];
  }

  /**
   * @dataProvider providerCoreOptionVersionParsing
   */
  public function testCoreOptionVersionParsing($status_code, $value, $display) {
    $this->versionParser = new VersionParser();
    $tester = $this->createCommandTester();

    $this->executeCommand($tester, FixtureInitCommand::getDefaultName(), [
      '--core' => $value,
    ]);

    $this->assertEquals($status_code, $tester->getStatusCode(), 'Returned correct status code.');
    $this->assertEquals($display, $tester->getDisplay(), 'Displayed correct output.');
  }

  public function providerCoreOptionVersionParsing() {
    $error_message = 'Error: Invalid value for "--core" option: "%s".' . PHP_EOL
      . 'Hint: Acceptable values are "PREVIOUS_RELEASE", "PREVIOUS_DEV", "CURRENT_RECOMMENDED", "CURRENT_DEV", "NEXT_RELEASE", "NEXT_DEV", or any version string Composer understands.' . PHP_EOL;
    return [
      [StatusCodes::OK, self::CORE_VALUE_LITERAL_PREVIOUS_RELEASE, ''],
      [StatusCodes::OK, self::CORE_VALUE_LITERAL_CURRENT_RECOMMENDED, ''],
      [StatusCodes::OK, self::CORE_VALUE_LITERAL_CURRENT_DEV, ''],
      [StatusCodes::OK, self::CORE_VALUE_LITERAL_NEXT_RELEASE, ''],
      [StatusCodes::OK, '^1.0', ''],
      [StatusCodes::OK, '~1.0', ''],
      [StatusCodes::OK, '>=1.0', ''],
      [StatusCodes::OK, 'dev-topic-branch', ''],
      [StatusCodes::ERROR, 'garbage', sprintf($error_message, 'garbage')],
      [StatusCodes::ERROR, '1.0.x-garbage', sprintf($error_message, '1.0.x-garbage')],
    ];
  }

  /**
   * @dataProvider providerIgnorePatchFailureOption
   */
  public function testIgnorePatchFailureOption($options, $num_calls) {
    $this->fixtureCreator
      ->setComposerExitOnPatchFailure(FALSE)
      ->shouldBeCalledTimes($num_calls);
    $this->fixtureCreator
      ->create()
      ->shouldBeCalledTimes(1);
    $tester = $this->createCommandTester();

    $this->executeCommand($tester, FixtureInitCommand::getDefaultName(), $options);

    $this->assertEquals("", $tester->getDisplay(), 'Displayed correct output.');
    $this->assertEquals(StatusCodes::OK, $tester->getStatusCode(), 'Returned correct status code.');
  }

  public function providerIgnorePatchFailureOption() {
    return [
      [['--ignore-patch-failure' => TRUE], 1],
      [[], 0],
    ];
  }

  public function testFixtureCreationFailure() {
    $exception_message = 'Failed to create fixture.';

    $this->fixtureCreator
      ->create(Argument::any())
      ->willThrow(new OrcaException($exception_message));
    $tester = $this->createCommandTester();

    $this->executeCommand($tester, FixtureInitCommand::getDefaultName());

    $this->assertEquals(StatusCodes::ERROR, $tester->getStatusCode(), 'Returned correct status code.');
    $this->assertContains("[ERROR] {$exception_message}", $tester->getDisplay(), 'Displayed correct output.');
  }

  public function testPreferSourceOption() {
    $this->fixtureCreator
      ->setPreferSource(TRUE)
      ->shouldBeCalledTimes(1);
    $this->fixtureCreator
      ->create()
      ->shouldBeCalledTimes(1);
    $tester = $this->createCommandTester();

    $this->executeCommand($tester, FixtureInitCommand::getDefaultName(), ['--prefer-source' => TRUE]);

    $this->assertEquals('', $tester->getDisplay(), 'Displayed correct output.');
    $this->assertEquals(StatusCodes::OK, $tester->getStatusCode(), 'Returned correct status code.');
  }

  public function testSutPreconditionTestFailure() {
    $sut_name = 'drupal/example';
    $exception_message = 'Failed to create fixture.';

    $this->fixture->exists()
      ->willReturn(TRUE);
    $this->fixtureRemover
      ->remove()
      ->shouldNotBeCalled();
    $this->sutPreconditionsTester
      ->test($sut_name)
      ->willThrow(new OrcaException($exception_message));
    $tester = $this->createCommandTester();

    $this->executeCommand($tester, FixtureInitCommand::getDefaultName(), [
      '--force' => TRUE,
      '--sut' => $sut_name,
    ]);

    $this->assertEquals(StatusCodes::ERROR, $tester->getStatusCode(), 'Returned correct status code.');
    $this->assertContains("[ERROR] {$exception_message}", $tester->getDisplay(), 'Displayed correct output.');
  }

  private function createCommandTester(): CommandTester {
    $application = new Application();
    /** @var \Acquia\Orca\Utility\DrupalCoreVersionFinder $drupal_core_version_finder */
    $drupal_core_version_finder = $this->drupalCoreVersionFinder->reveal();
    /** @var \Acquia\Orca\Fixture\FixtureCreator $fixture_creator */
    $fixture_creator = $this->fixtureCreator->reveal();
    /** @var \Acquia\Orca\Fixture\FixtureRemover $fixture_remover */
    $fixture_remover = $this->fixtureRemover->reveal();
    /** @var \Acquia\Orca\Fixture\Fixture $fixture */
    $fixture = $this->fixture->reveal();
    /** @var \Acquia\Orca\Fixture\PackageManager $package_manager */
    $package_manager = $this->packageManager->reveal();
    /** @var \Acquia\Orca\Fixture\SutPreconditionsTester $sut_preconditions_tester */
    $sut_preconditions_tester = $this->sutPreconditionsTester->reveal();
    /** @var \Composer\Semver\VersionParser $version_parser */
    $version_parser = ($this->versionParser instanceof VersionParser) ? $this->versionParser : $this->versionParser->reveal();
    $application->add(new FixtureInitCommand($drupal_core_version_finder, $fixture, $fixture_creator, $fixture_remover, $package_manager, $sut_preconditions_tester, $version_parser));
    /** @var \Acquia\Orca\Command\Fixture\FixtureInitCommand $command */
    $command = $application->find(FixtureInitCommand::getDefaultName());
    $this->assertInstanceOf(FixtureInitCommand::class, $command, 'Instantiated class.');
    return new CommandTester($command);
  }

}
