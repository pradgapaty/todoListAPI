<?php

declare(strict_types = 1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskListModel extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = "taskList";
    protected $fillable = [
        'userToken',
        'taskId',
        'status',
        'priority',
        'title',
        'description',
        'completedAt',
        "createdAt",
    ];
}
