<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\TaskListController;
use App\Http\Controllers\API\RegisterController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

//routes for auth by token (temporary disabled)
// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

// Route::post('register', [RegisterController::class, "register"]);
// Route::post('login', [RegisterController::class, "login"]);

// Route::middleware('auth:api')->group( function () {
//     Route::get("getAllTasks", [TaskListController::class, "index"]);
//     Route::get("getTasksByToken/{token}", [TaskListController::class, "getTasksByToken"]);
//     Route::delete("deleteTask/{taskId}/{userToken}", [TaskListController::class, "deleteTask"]);
//     Route::put("addNewTask", [TaskListController::class, "addNewTask"]);
//     Route::post("updateTaskStatus", [TaskListController::class, "updateTaskStatus"]);
//     Route::post("updateTaskPriority", [TaskListController::class, "updateTaskPriority"]);
// });

    //routes whithout auth
    Route::post("getTasks", [TaskListController::class, "getTasks"]);
    Route::delete("deleteTask/{taskId}/{userToken}", [TaskListController::class, "deleteTask"]);
    Route::put("addNewTask", [TaskListController::class, "addNewTask"]);
    Route::post("updateTaskStatus", [TaskListController::class, "updateTaskStatus"]);
    Route::post("updateTaskPriority", [TaskListController::class, "updateTaskPriority"]);
    Route::post("updateTaskTitle", [TaskListController::class, "updateTaskTitle"]);
    Route::post("updateTaskDescription", [TaskListController::class, "updateTaskDescription"]);
    Route::post("multiUpdateTask", [TaskListController::class, "multiUpdateTask"]);
    Route::put("addSubTask", [TaskListController::class, "addSubTask"]);
    Route::post("getSubTasks", [TaskListController::class, "getSubTasks"]);
    