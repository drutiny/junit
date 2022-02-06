<?php

namespace Drutiny\JUnit\Format;

use Drutiny\Report\Format;
use Drutiny\Report\FilesystemFormatInterface;
use Drutiny\Report\FormatInterface;
use Drutiny\Profile;
use Drutiny\AssessmentInterface;
use Llaumgui\JunitXml\JunitXmlTestSuites;
use Llaumgui\JunitXml\JunitXmlValidation;
use Symfony\Component\Console\Output\StreamOutput;

class JUnit extends Format implements FilesystemFormatInterface {
  protected string $name = 'junit';
  protected string $extension = 'xml';
  protected JunitXmlTestSuites $testSuite;
  protected string $directory;

  /**
   * {@inheritdoc}
   */
  public function setWriteableDirectory(string $dir):void
  {
    $this->directory = $dir;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtension():string
  {
    return $this->extension;
  }

  protected function configure()
  {
      $this->twig = $this->container->get('twig');
      $this->testSuite = new JunitXmlTestSuites('Test results');
  }

  public function render(Profile $profile, AssessmentInterface $assessment):FormatInterface
  {
    $suite = $this->testSuite->addTestSuite($profile->title);

    foreach ($assessment->getResults() as $response) {
      $policy = $response->getPolicy();

      $test = $suite->addTest($policy->title);
      $test->incAssertions();

      switch ($response->getType()) {
        case 'not-applicable':
          $test->addSkipped($policy->title.': Not applicable.');
          break;

        case 'error':
          $test->addError($policy->title.strtr(': exception', $response->getTokens()));

        case 'failure':
          $test->addFailure($this->twig->createTemplate($policy->failure)->render($response->getTokens()));
          break;
      }

      $test->finish();
    }
    $suite->finish();
    return $this;
  }

  public function write():iterable
  {
    $filepath = $this->directory . '/' . $this->namespace . '.' . $this->extension;
    $stream = new StreamOutput(fopen($filepath, 'w'));
    $junit = $this->testSuite->getXml();
    $stream->write($junit);
    yield $filepath;
  }

}
