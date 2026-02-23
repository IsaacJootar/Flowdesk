<?php

namespace App\Services\RequestCommunication;

use App\Domains\Requests\Models\RequestCommunicationLog;

interface ChannelDeliveryAdapter
{
    public function deliver(RequestCommunicationLog $log): DeliveryResult;
}

