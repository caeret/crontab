<?php

namespace Overnil\Cron;


use Overnil\EventLoop\Factory;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

class Crontab
{

    private $logger;

    private $loop;

    private $interval = 60;

    /**
     * @var Task[]
     */
    private $tasks = [];

    public function __construct(LoggerInterface $logger, LoopInterface $loop = null)
    {
        $this->logger = $logger;
        if (is_null($loop)) {
            $loop = Factory::create();
        }
        $this->loop = $loop;
        $this->timers = new \SplObjectStorage();
    }

    public function addTask(Task $task)
    {
        $this->tasks[$task->getName()] = $task;
        return $this;
    }

    public function addTasks(array $tasks)
    {
        foreach ($tasks as $task) {
            $this->addTask($task);
        }
        return $this;
    }

    public function removeTask($name)
    {
        if (isset($this->tasks[$name])) {
            $task = $this->tasks[$name];
            unset($this->tasks[$name]);
            return $task;
        }
        return null;
    }

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

    public function run()
    {
        $this->loop->addPeriodicTimer($this->interval, function () {
            $this->loop->addTimer($this->interval - time() % $this->interval, function() {
                foreach ($this->tasks as $task) {
                    if ($task->needRun()) {
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
        
        $this->loop->addPeriodicTimer($this->interval, function () {
            while ($pid = pcntl_waitpid(0, $status, WNOHANG) > 0) {
                $this->logger->info("task process exit: [{$pid}]{$status}.");
            }
        });

        $this->loop->run();
    }

}