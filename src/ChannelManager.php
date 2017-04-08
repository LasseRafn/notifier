<?php

namespace Mediumart\Notifier;

use ReflectionClass;
use InvalidArgumentException;
use Mediumart\Notifier\Contracts\Channels\Factory;
use Mediumart\Notifier\Contracts\Channels\Dispatcher;
use Illuminate\Notifications\ChannelManager as Manager;

class ChannelManager extends Manager
{
    /**
     * Registered Channels factories.
     *
     * @var array
     */
    protected $channels = [];

    /**
     * Register a new channel driver.
     *
     * @param  string $channel
     * @return void
     */
    public function register($channel)
    {
        $this->throwExceptionIfChannelNotAValidFactory($channel);

        if (array_search($channel = ltrim($channel, '\\'), $this->channels) === false) {
            $this->channels[] = $channel;
        }
    }

    /**
     * Create a new driver instance.
     *
     * @param  string $driver
     * @return mixed
     * @throws InvalidArgumentException
     */
    protected function createDriver($driver)
    {
        $channel = $this->createChannelDriver($driver);

        if ($channel instanceof Dispatcher) {
            return $channel;
        }

        // We'll check to see if a creator method exists for the given driver. If not we
        // will check for a custom driver creator, which allows developers to create
        // drivers using their own customized driver creator Closure to create it.
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        if (class_exists($driver)) {
            return $this->app->make($driver);
        }

        throw new InvalidArgumentException("Driver [$driver] not supported.");
    }

    /**
     * Create a new channel driver.
     *
     * @param  $driver
     * @return null|\Mediumart\Notifier\Contracts\Channels\Dispatcher
     */
    protected function createChannelDriver($driver)
    {
        foreach ($this->channels as $channel) {
            if ($channel::canHandleNotification($driver) &&
                $channel = $channel::createDriver($driver)) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * Get all of the registered channels.
     *
     * @return array
     */
    public function getChannels()
    {
        return $this->channels;
    }

    /**
     * Invalid channel handler.
     *
     * @param $channel
     * throws InvalidArgumentException
     */
    protected function throwExceptionIfChannelNotAValidFactory($channel)
    {
        if (!(new ReflectionClass($channel))->implementsInterface(Factory::class)) {
            throw new InvalidArgumentException(sprintf(
                "class [$channel] is not a valid implementation of '%s' interface.",
                Factory::class
            ));
        }
    }
}
