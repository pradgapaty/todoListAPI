<?php

declare(strict_types = 1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskListResources extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'userToken' => $this->userToken,
            'taskId' => $this->taskId,
            'status' => $this->status,
            'priority' => $this->priority,
            'title' => $this->title,
            'description' => $this->description,
            'createdAt' => $this->createdAt,
            'completedAt' => $this->completedAt,
        ];
    }
}
