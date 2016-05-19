<?php

namespace Overnil\Cron;


use Cron\CronExpression;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class Task
{

    /**
     * The task name.
     *
     * @var string
     */
    private $name;

    /**
     * The cron expression.
     *
     * @var string
     */
    private $expression;

    /**
     * The command string.
     *
     * @var string
     */
    private $command;

    /**
     * The output logger.
     *
     * @var LoggerInterface
     */
    private $out;

    /**
     * The error logger.
     *
     * @var LoggerInterface
     */
    private $err;

    /**
     * The task timeout.
     *
     * @var int
     */
    private $timeout;

    /**
     * The cron expression.
     * 
     * @var CronExpression
     */
    private $_cronExpression;

    /**
     * Task constructor.
     * 
     * @param string $name
     * @param string $expression
     * @param string $command
     * @param LoggerInterface $out
     * @param LoggerInterface $err
     * @param int $timeout
     */
    public function __construct($name, $expression, $command, LoggerInterface $out, LoggerInterface $err, $timeout = 60)
    {
        $this->name = $name;
        $this->expression = $expression;
        $this->command = $command;
        $this->out = $out;
        $this->err = $err;
        $this->timeout = (int)$timeout;
    }

    /**
     * Get the CronExpression instance by the task expression.
     * 
     * @return CronExpression
     */
    private function getCronExpression()
    {
        if (!isset($this->_cronExpression)) {
            $this->_cronExpression = CronExpression::factory($this->expression);
        }
        return $this->_cronExpression;
    }

    /**
     * Check if the task should be executed.
     * 
     * @param string $now
     * @return bool
     */
    public function isDue($now = 'now')
    {
        return $this->getCronExpression()->isDue($now);
    }

    /**
     * Run the task.
     * 
     * @return bool
     */
    public function run()
    {
        $process = new Process($this->command);
        $process->run();

        $prefix = "Task with name [{$this->name}]";

        if ($process->isSuccessful()) {
            $this->out->info("{$prefix}\n{$process->getOutput()}", $this->getLogContext());
        } else {
            $this->err->error("{$prefix}\n{$process->getErrorOutput()}", $this->getLogContext());
        }

        return $process->isSuccessful();
    }

    /**
     * Get the log context.
     * 
     * @return array
     */
    private function getLogContext()
    {
        return [
            'name' => $this->name,
            'expression' => $this->expression,
            'command' => $this->command,
            'timeout' => $this->timeout,
        ];
    }

    /**
     * Get the task name.
     * 
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
    
}