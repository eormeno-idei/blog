<?php

namespace App\Support\UI;

use Idei\Usim\UIChangesCollector;

class AppUIChangesCollector extends UIChangesCollector
{
    /**
     * @param  array<array-key, mixed>  $change
     */
    public function add(array $change = []): void
    {
        $specialKeys = [
            'navigate',
            'clear_container',
            'clear_uploaders',
            'redirect',
            'toast',
            'modal',
            'update_modal',
            'action',
            'storage',
            'agent_context',
            'abort',
        ];

        foreach ($change as $key => $value) {
            $isComponent = is_numeric($key) || !in_array($key, $specialKeys, true);

            if ($isComponent && is_array($value) && isset($this->changes[$key]) && is_array($this->changes[$key])) {
                $this->changes[$key] = array_merge($this->changes[$key], $value);
            } else {
                $this->changes[$key] = $value;
            }
        }
    }
}

