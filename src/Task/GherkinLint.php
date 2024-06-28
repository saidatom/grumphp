<?php

declare(strict_types=1);

namespace GrumPHP\Task;

use GrumPHP\Formatter\ProcessFormatterInterface;
use GrumPHP\Runner\TaskResult;
use GrumPHP\Runner\TaskResultInterface;
use GrumPHP\Task\Config\ConfigOptionsResolver;
use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\GitPreCommitContext;
use GrumPHP\Task\Context\RunContext;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractExternalTask<ProcessFormatterInterface>
 */
class GherkinLint extends AbstractExternalTask
{
    public static function getConfigurableOptions(): ConfigOptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'directory' => 'features',
            'config' => null,
        ]);

        $resolver->addAllowedTypes('directory', ['string']);
        $resolver->addAllowedTypes('config', ['null', 'string']);
        $resolver->addAllowedValues('config', [null, 'text']);

        return ConfigOptionsResolver::fromOptionsResolver($resolver);
    }

    /**
     * {@inheritdoc}
     */
    public function canRunInContext(ContextInterface $context): bool
    {
        return $context instanceof GitPreCommitContext || $context instanceof RunContext;
    }

    /**
     * {@inheritdoc}
     */
    public function run(ContextInterface $context): TaskResultInterface
    {
        $config = $this->getConfig()->getOptions();
        $files = $context->getFiles()->extensions(['feature']);
        if (0 === \count($files)) {
            return TaskResult::createSkipped($this, $context);
        }

        $arguments = $this->processBuilder->createArgumentsForCommand('gherkinlint');
        $arguments->add('lint');
        $arguments->addOptionalArgument('--config=%s', $config['config']);
        $arguments->add($config['directory']);

        $process = $this->processBuilder->buildProcess($arguments);
        $process->run();

        if (!$process->isSuccessful()) {
            return TaskResult::createFailed($this, $context, $this->formatter->format($process));
        }

        return TaskResult::createPassed($this, $context);
    }
}