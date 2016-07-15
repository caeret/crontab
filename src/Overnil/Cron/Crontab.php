<?php

namespace Overnil\Cron;

use React\EventLoop\Factory;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

class Crontab
{

    /**
     * The crontab logger.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * The event loop instance.
     *
     * @var LoopInterface
     */
    private $loop;

    /**
     * The crontab tasks.
     *
     * @var Task[]
     */
    private $tasks = [];

    /**
     * Crontab constructor.
     *
     * @param LoggerInterface $logger
     * @param LoopInterface|null $loop
     */
    public function __construct(LoggerInterface $logger, LoopInterface $loop = null)
    {
        $this->logger = $logger;
        if (is_null($loop)) {
            $loop = Factory::create();
        }
        $this->loop = $loop;
        $this->timers = new \SplObjectStorage();
    }

    /**
     * Add a task to the crontab.
     *
     * @param Task $task
     * @return $this
     */
    public function addTask(Task $task)
    {
        $this->logger->info("added task \"{$task->getName()}\".");
        if (isset($this->tasks[$task->getName()])) {
            throw new \InvalidArgumentException("Task with name \"{$task->getName()}\" is exist.");
        }
        $this->tasks[$task->getName()] = $task;
        return $this;
    }

    /**
     * Add tasks to the crontab.
     *
     * @param Task[] $tasks
     * @return $this
     */
    public function addTasks(array $tasks)
    {
        foreach ($tasks as $task) {
            $this->addTask($task);
        }
        return $this;
    }

    /**
     * Remove a task by the given name.
     *
     * @param string $name
     * @return null|Task
     */
    public function removeTask($name)
    {
        if (isset($this->tasks[$name])) {
            $this->logger->info("removed task \"{$name}\".");
            $task = $this->tasks[$name];
            unset($this->tasks[$name]);
            return $task;
        }
        $this->logger->info("unable to remove task \"{$name}\": task does not exist.");
        return null;
    }

    /**
     * Remove tasks by the given names.
     *
     * @param array $names
     * @return Task[]
     */
    public function removeTasks(array $names)
    {
        $tasks = [];
        foreach ($names as $name) {
            if ($task = $this->removeTask($name)) {
                $tasks[] = $task;
            }
        }
        return $tasks;
    }

    /**
     * Run the crontab using the given interval.
     *
     * @param int $interval
     */
    public function run($interval = 60)
    {
        $this->logger->info("added crontab timer.");
        $this->loop->addPeriodicTimer($interval, function () use ($interval) {
            $this->loop->addTimer($interval - time() % $interval, function () {
                foreach ($this->tasks as $task) {
                    if ($task->isDue()) {
                        $pid = pcntl_fork();
                        if ($pid > 0) {
                            continue;
                        } elseif ($pid === 0) {
                            $task->run();
                            exit();
                        } else {
                            $this->logger->error('Unable to fork process for running tasks.');
                        }
                    }
                }
            });
        });

        $this->logger->info("added task process receiving timer.");
        $this->loop->addPeriodicTimer($interval, function () use ($interval) {
            while ($pid = pcntl_waitpid(0, $status, WNOHANG) > 0) {
                $this->logger->info("task process exit: [{$pid}]{$status}.");
            }
        });

        $after = $interval + time() % $interval;
        $this->logger->info("crontab will run with interval {$interval}s after {$after}s.");
        $this->loop->run();
    }

    public function stop()
    {
        $this->loop->stop();
    }
}
