<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue;

use TondbadSwoole\Queue\Flow\Flow;

class FlowProducer
{
    public function __construct(
        private readonly QueueManager $queueManager,
    ) {
    }

    public function add(Flow $flow, ?string $connection = null, ?string $queue = null): mixed
    {
        $queueInstance = $this->queueManager->connection($connection);
        $queueName = $flow->queue ?? $queue;

        $parent = $flow->job;

        if ($queueName !== null) {
            $parent->onQueue($queueName);
        }

        $parent->setChildrenCount(count($flow->children));

        $parentId = $queueInstance->add($parent, $queueName);

        foreach ($flow->children as $child) {
            $childJob = $child->job;
            $childJob->setParentId((int) $parentId);
            $childJob->removeOnComplete(false);

            if ($queueName !== null) {
                $childJob->onQueue($queueName);
            }

            $queueInstance->add($childJob, $queueName, $child->options);
        }

        $queueInstance->emit('flow_added', [
            'parent' => $parent,
            'children' => $flow->children,
            'queue' => $queueName,
        ]);

        return $parentId;
    }
}
