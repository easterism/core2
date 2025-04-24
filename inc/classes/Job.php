<?php

namespace Core2;

class Job
{
    private static $payload;
    private static $job;
    private static $unique;

    public function __construct($job, $payload = [], $unique = null) {
        self::$job = $job;
        self::$payload = json_encode($payload);
        self::$unique = $unique;
    }

    public function returnCode() {}

    /**
     * Sets the return value for this job, indicates how the job completed.
     *
     * @link https://php.net/manual/en/gearmanjob.setreturn.php
     * @param int $gearman_return_t A valid Gearman return value
     * @return bool Description
     */
    public function setReturn($gearman_return_t) {}

    /**
     * Sends data to the job server (and any listening clients) for this job.
     *
     * @link https://php.net/manual/en/gearmanjob.senddata.php
     * @param string $data Arbitrary serialized data
     * @return bool
     */
    public function sendData($data) {}

    /**
     * Sends a warning for this job while it is running.
     *
     * @link https://php.net/manual/en/gearmanjob.sendwarning.php
     * @param string $warning A warning messages
     * @return bool
     */
    public function sendWarning($warning) {}

    /**
     * Sends status information to the job server and any listening clients. Use this
     * to specify what percentage of the job has been completed.
     *
     * @link https://php.net/manual/en/gearmanjob.sendstatus.php
     * @param int $numerator The numerator of the percentage completed expressed as a
     *        fraction
     * @param int $denominator The denominator of the percentage completed expressed as
     *        a fraction
     * @return bool
     */
    public function sendStatus($numerator, $denominator) {}

    /**
     * Sends result data and the complete status update for this job.
     *
     * @link https://php.net/manual/en/gearmanjob.sendcomplete.php
     * @param string $result Serialized result data
     * @return bool
     */
    public function sendComplete($result) {}

    /**
     * Sends the supplied exception when this job is running.
     *
     * @link https://php.net/manual/en/gearmanjob.sendexception.php
     * @param string $exception An exception description
     * @return bool
     */
    public function sendException($exception) {}

    /**
     * Sends failure status for this job, indicating that the job failed in a known way
     * (as opposed to failing due to a thrown exception).
     *
     * @link https://php.net/manual/en/gearmanjob.sendfail.php
     * @return bool
     */
    public function sendFail() {}

    /**
     * Returns the opaque job handle assigned by the job server.
     *
     * @link https://php.net/manual/en/gearmanjob.handle.php
     * @return string An opaque job handle
     */
    public function handle() {
        return self::$job;
    }

    /**
     * Returns the function name for this job. This is the function the work will
     * execute to perform the job.
     *
     * @link https://php.net/manual/en/gearmanjob.functionname.php
     * @return string The name of a function
     */
    public function functionName() {}

    /**
     * Returns the unique identifiter for this job. The identifier is assigned by the
     * client.
     *
     * @link https://php.net/manual/en/gearmanjob.unique.php
     * @return string An opaque unique identifier
     */
    public function unique() {
        return self::$unique;
    }

    /**
     * Returns the workload for the job. This is serialized data that is to be
     * processed by the worker.
     *
     * @link https://php.net/manual/en/gearmanjob.workload.php
     * @return string Serialized data
     */
    public function workload() {
        //TODO возвращать массив
        return self::$payload;
    }

    /**
     * Returns the size of the job's work load (the data the worker is to process) in
     * bytes.
     *
     * @link https://php.net/manual/en/gearmanjob.workloadsize.php
     * @return int The size in bytes
     */
    public function workloadSize() {
        return strlen(self::$payload);
    }
}