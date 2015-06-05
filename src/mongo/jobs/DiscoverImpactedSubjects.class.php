<?php

namespace Tripod\Mongo\Jobs;

/**
 * Class DiscoverImpactedSubjects
 * @package Tripod\Mongo\Jobs
 */
class DiscoverImpactedSubjects extends JobBase {

    /**
     * Run the DiscoverImpactedSubjects job
     * @throws \Exception
     */
    public function perform()
    {
        try
        {

            $this->debugLog("DiscoverImpactedSubjects::perform() start");

            $timer = new \Tripod\Timer();
            $timer->start();

            $this->validateArgs();

            // set the config to what is received
            \Tripod\Mongo\Config::setConfig($this->args["tripodConfig"]);

            $tripod = $this->getTripod($this->args["storeName"],$this->args["podName"]);

            $operations = $this->args['operations'];
            $modifiedSubjects = array();

            $subjectsAndPredicatesOfChange = $this->args['changes'];

            foreach($operations as $op)
            {
                $composite = $tripod->getComposite($op);
                $modifiedSubjects = array_merge($modifiedSubjects,$composite->getImpactedSubjects($subjectsAndPredicatesOfChange,$this->args['contextAlias']));
            }

            if(!empty($modifiedSubjects)){
                /* @var $subject \Tripod\Mongo\ImpactedSubject */
                foreach ($modifiedSubjects as $subject) {
                    $resourceId = $subject->getResourceId();
                    $this->debugLog("Adding operation {$subject->getOperation()} for subject {$resourceId[_ID_RESOURCE]} to queue ".\Tripod\Mongo\Config::getApplyQueueName());
                    $this->submitJob(\Tripod\Mongo\Config::getApplyQueueName(),"\Tripod\Mongo\Jobs\ApplyOperation",array(
                        "subject"=>$subject->toArray(),
                        "tripodConfig"=>$this->args["tripodConfig"]
                    ));
                }
            }

            // stat time taken to process item, from time it was created (queued)
            $timer->stop();
            $this->getStat()->timer(MONGO_QUEUE_DISCOVER_SUCCESS,$timer->result());
            $this->debugLog("DiscoverImpactedSubjects::perform() done in {$timer->result()}ms");

        }
        catch(\Exception $e)
        {
            $this->errorLog("Caught exception in ".get_class($this).": ".$e->getMessage());
            throw $e;
        }
    }

    /**
     * Validate args for DiscoverImpactedSubjects
     * @return array
     */
    protected function getMandatoryArgs()
    {
        return array("tripodConfig","storeName","podName","changes","operations","contextAlias");
    }
}