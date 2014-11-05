<?php

/*
 * This file is part of HeriJobQueueBundle.
 *
 * (c) Alexandre Mogère
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Heri\Bundle\JobQueueBundle\Service;

use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;

class QueueService
{
    /**
     * var ZendQueue\Adapter\AbstractAdapter
     */
    public $adapter;

    /**
     * var LoggerInterface
     */
    protected $logger;

    protected
        $command,
        $output,
        $config,
        $queue
    ;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }


    public function attach($name)
    {
        $this->config = array(
            'name' => $name,
        );

        if (!$this->queue instanceof \ZendQueue\Queue) {
            $this->queue = new \ZendQueue\Queue($this->adapter, $this->config);
        } else {
            $this->queue->createQueue($name);
        }
    }

    public function receive($maxMessages = 1)
    {
        $messages = $this->queue->receive($maxMessages);
        if ($messages && $messages->count() > 0) {
            $this->execute($messages);
        }
    }

    /**
     * @param array $args
     */
    public function push(array $args)
    {
        if (!is_null($this->queue)) {
            $this->queue->send(\Zend\Json\Encoder::encode($args));
        }
    }

    public function flush()
    {
        $this->adapter->flush();
    }

    public function setAdapter(ZendQueue\Adapter\AbstractAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function setCommand($command)
    {
        $this->command = $command;
    }

    public function setOutput($output)
    {
        $this->output = $output;
    }

    protected function execute($messages)
    {
        foreach ($messages as $message) {
            $output = date('H:i:s') . ' - ' . ($message->failed ? 'failed' : 'new');
            $output .= '['.$message->id.']';

            $this->output->writeLn('<comment>' . $output . '</comment>');

            $args = \Zend\Json\Encoder::decode($message->body);

            try {
                $argument = isset($args['argument']) ? (array) $args['argument'] : array();
                $input = new ArrayInput(array_merge(array(''), $argument));
                $command = $this->command->getApplication()->find($args['command']);
                $command->run($input, $this->output);

                $this->queue->deleteMessage($message);
                $this->output->writeLn('<info>Ended</info>');
            } catch (\Exception $e) {
                $this->adapter->logException($message, $e);
                $this->output->writeLn('<error>Failed</error> ' . $e->getMessage());
            }
        }
    }

    /**
     * @param array $args
     * @deprecated
     */
    public function sync(array $args)
    {
        return $this->push($args);
    }

    /**
     * @param string $name
     * @deprecated
     */
    public function configure($name)
    {
        return $this->attach($name);
    }

}
