<?php

declare(strict_types = 1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TaskListModel;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Http\Resources\TaskListResources;
use Validator;
use Str;

class TaskListController extends BaseController
{

    private $statusesList = [
        "New",
        "In progress",
        "Done",
    ];

    private $priorityList = [
        1,
        2,
        3,
        4,
        5,
    ];

    private $orderByList = [
        "createdAt",
        "completedAt",
        "priority",
    ];

    private $titleMaxLenth = 100;
    private $descriptionMaxLenth = 255;
    private function getNewTaskStatus(): string
    {
        return $this->statusesList[0];
    }

    private function getInProgressTaskStatus(): string
    {
        return $this->statusesList[1];
    }

    private function getDoneTaskStatus(): string
    {
        return $this->statusesList[2];
    }

    /**
     * Show user tasks
     *
     * @param string $token
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTasks(Request $request)
    {
        //set default query limit
        $limit = 1000;

        $fieldsForSearch = [
            "taskId",
            "status",
            "priority",
            "title",
            "description",
        ];
        $input = $request->all();
        $validator = Validator::make($input, [
            'userToken' => 'required',
        ]);

        if ($validator->fails()) {
            $response = $this->sendError("Validation Error", $validator->errors());       
        } else {
            $userToken = $input["userToken"];
            $queryRes = TaskListModel::where("userToken", $userToken);

            //filter by empty status and/or priority and/or title
            if (isset($input["filterByEmptyStatus"])) {
                $queryRes->where("status", "!=", "");
            }

            if (isset($input["filterByEmptyPriority"])) {
                $queryRes->where("priority", "!=", 0);
            }

            if (isset($input["filterByEmptyTitle"])) {
                $queryRes->where("title", "!=", "");
            }

            foreach ($input as $inputKey => $inputValue) {
                if (in_array($inputKey, $fieldsForSearch)) {
                    if ($inputKey === "title") {
                        $queryRes->where($inputKey, "like", "%" . $inputValue . "%");
                    } else {
                        $queryRes->where($inputKey, $inputValue);
                    }
                }
            }

            if (!empty($input["orderBy"])) {
                if (!in_array($input["orderBy"], $this->orderByList)) {
                    return $this->sendError('Incorrect orderBy param. Avaliable params: {' . print_r($this->orderByList, true) . '}');
                }
                $queryRes->orderBy($input["orderBy"]);
            }
            $queryRes->limit($limit);
            $tasks = $queryRes->get();

            if (count($tasks) > 0) {
                //convert dateTime for createdAt, completedAt fields
                foreach ($tasks as $taskKey => $taskValue) {
                    if (!empty($taskValue["createdAt"])) {
                        $tasks[$taskKey]["createdAt"] = $this->unixtimeCorvert((int) $taskValue["createdAt"], "Y-m-d H:i:s");
                        
                    }
                    if (!empty($taskValue["completedAt"])) {
                        $tasks[$taskKey]["completedAt"] = $this->unixtimeCorvert((int) $taskValue["completedAt"], "Y-m-d H:i:s");
                    }
                }

                $response = $this->sendResponse(TaskListResources::collection($tasks), "Founded {" . count($tasks) . "} tasks");
            } else {
                $response = $this->sendError("Cannot find any tasks by input params");
            }
        }

        return $response;
    }

    /**
     * Show user tasks
     *
     * @param string $token
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubTasks(Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'taskId' => 'required',
            'userToken' => 'required',
        ]);

        if ($validator->fails()) {
            $response = $this->sendError("Validation Error", $validator->errors());       
        } else {
            $taskId = (int) $input["taskId"];
            $userToken = $input["userToken"];
            $subTasks = $this->getUserSubTasks($taskId, $userToken);

            if (count($subTasks) > 0) {
                $response = $this->sendResponse(TaskListResources::collection($subTasks), "Founded {" . count($subTasks) . "} subtasks");
            } else {
                $response = $this->sendError("Cannot find any subtasks by input params");
            }
        }

        return $response;
    }

    /**
     * Add new task
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addNewTask(Request $request)
    {
        $tokenParamExist = true;
        $input = $request->all();

        //set token, if user used API firstly
        if (empty($input["userToken"])) {
            $input["userToken"] = md5(uniqid());
            $tokenParamExist = false;
        }

        //set default values
        $input["createdAt"] = time();
        $input["completedAt"] = 0;

        $insertId = TaskListModel::insertGetId($input);

        if ($tokenParamExist) {
            $response = $this->sendResponse(["taskId" => $insertId], 'Task created successfully');
        } else {
            $response = $this->sendResponse(["taskId" => $insertId, "userToken" => $input["userToken"]], "Warning! For show, edit and delete yours tasks - save your token (identification)");
        }

        return $response;
    }

    /**
     * Add subtask
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addSubTask(Request $request)
    {
        $taskId = 0;
        $input = $request->all();

        $validator = Validator::make($input, [
            'taskId' => 'required',
            'userToken' => 'required',
        ]);

        if ($validator->fails()){
            $response = $this->sendError('Validation Error.', $validator->errors());       
        } else {
            $taskId = $input["taskId"];
            $userToken = $input["userToken"];

            $resArr = TaskListModel::where("taskId", $taskId)->get()->toArray();

            if (count($resArr) > 0) {
                if ($resArr[0]["userToken"] === $userToken) {
                    if (!empty($resArr[0]["parrentlyTaskId"])) {
                        $response = $this->sendError("Task with id {" . $taskId . "} already is subtask. You cannot add subtask for subtask");
                    } else {
                        //set default values
                        $input["createdAt"] = time();
                        $input["completedAt"] = 0;
                        $input["parrentlyTaskId"] = (int) $taskId;

                        //remove taskId key before insert
                        unset($input["taskId"]);
                        $insertId = TaskListModel::insertGetId($input);
                        $response = $this->sendResponse(["subtaskId" => $insertId], 'Subtask created Successfully');
                    }

                } else {
                    $response = $this->sendError("You cannot add subtask for tasks other users");
                }
            } else {
                $response = $this->sendError("Task with id {" . $taskId . "} not founded. Cannot create subtask");
            }
        }

        return $response;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $taskId
     * @return \Illuminate\Http\Response
     */
    public function deleteTask(string $taskId, string $userToken)
    {
        //TODO: add autodelete subtasks
        $result = 0;

        if (!empty($taskId)) {
            $resArr = TaskListModel::where("taskId", $taskId)->get()->toArray();

            if (count($resArr) > 0) {
                //user cannot delete other tasks
                if ($resArr[0]["userToken"] === $userToken) {
                    //user cannot delete task with status "Done"
                    if (!empty($resArr[0]["status"]) && $resArr[0]["status"] === $this->getDoneTaskStatus()) {
                        $response = $this->sendError("Task with id {" . $taskId . "} have status {" . $this->getDoneTaskStatus() . "} and cannot be deleted");
                    } else {
                        $taskForDelete = TaskListModel::where("taskId", $taskId);
                        $result = $taskForDelete->delete();

                        if ($result > 0) {
                            $response = $this->sendResponse([], 'Task deleted successfully');
                        } else {
                            $response = $this->sendError('Cannot delete task...');
                        }
                    }
                } else {
                    $response = $this->sendError("You cannot delete tasks other users");
                }
            } else {
                $response = $this->sendError("Task with id {" . $taskId . "} not founded");
            }
        } else {
            $response = $this->sendError('Empty task id');
        }

        return $response;
    }

    /**
     * Update task status
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateTaskStatus(Request $request)
    {
        $result = 0;
        $input = $request->all();
        $validator = Validator::make($input, [
            'taskId' => 'required',
            'taskStatus' => 'required',
            'userToken' => 'required',
        ]);
   
        if ($validator->fails()){
            $response = $this->sendError('Validation Error.', $validator->errors());       
        } else {
            $taskId = (int) $input["taskId"];
            $taskStatus = $input["taskStatus"];
            $userToken = $input["userToken"];

            if (!in_array($taskStatus, $this->statusesList)) {
                $response = $this->sendError('Incorrect task status. Avaliable statuses: {' . print_r($this->statusesList, true) . '}');
            } else {
                $res = TaskListModel::where("taskId", $taskId)->get()->toArray();

                if (count($res) > 0) {
                    //additional function, user cannot change task status if status is Done (because after status is changed he can delete task)
                    $statusinDb = $res[0]["status"];

                    if ($statusinDb === $this->getDoneTaskStatus()) {
                        $response = $this->sendError("User cannot change status. Task already Done");
                    } else {
                        //user cannot modify other tasks
                        if ($res[0]["userToken"] === $userToken) {
                            $subTaskMarker = 0;
                            //user cannot update status "Done" if task have subtasks with statuses not "Done"
                            if ($taskStatus === $this->getDoneTaskStatus()) {
                                $subTasks = $this->getUserSubTasks($taskId, $userToken)->toArray();
    
                                if (!empty($subTasks)) {
                                    foreach ($subTasks as $taskKey => $task) {
                                        if ($task["status"] != $this->getDoneTaskStatus()) {
                                            $subTaskMarker = 1;
                                            break;
                                        }
                                    }
    
                                    if ($subTaskMarker > 0) {
                                        $response = $this->sendError("User cannot change status to {" . $this->getDoneTaskStatus() . "} if have subtasks with status not {" . $this->getDoneTaskStatus() . "}");
                                    }
                                }
                            }

                            if ($subTaskMarker === 0) {
                                $result = TaskListModel::where("taskId", $taskId)->update(["status" => $taskStatus]);
                            
                                if ($result > 0) {
                                    //update completedAt if status changed to Done
                                    if ($taskStatus === $this->getDoneTaskStatus()) {
                                        TaskListModel::where("taskId", $taskId)->update(["completedAt" => time()]);;
                                    }
                                    $response = $this->sendResponse([], 'Task Updated Successfully.');
                                } else {
                                    $res = TaskListModel::where("taskId", $taskId)->where("userToken", $userToken)->get()->toArray();
            
                                    if (!empty($res[0]["status"]) && $res[0]["status"] === $taskStatus) {
                                        $response = $this->sendError("Task with id {" . $taskId . "} already have status {" . $taskStatus . "}");
                                    }
                                }
                            }
                        } else {
                            $response = $this->sendError("You cannot modify status of tasks other users");
                        }
                    }
                } else {
                    $response = $this->sendError("Task with id {" . $taskId . "} and userToken {" . $userToken . "} not founded...");
                }
            }
        }

        return $response;
    }

    /**
     * Update task proiority
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateTaskPriority(Request $request)
    {
        $result = 0;
        $input = $request->all();

        $validator = Validator::make($input, [
            'taskId' => 'required',
            'priority' => 'required',
            'userToken' => 'required',
        ]);

        if ($validator->fails()){
            $response = $this->sendError('Validation Error.', $validator->errors());       
        } else {
            $taskId = $input["taskId"];
            $priority = (int) $input["priority"];
            $userToken = $input["userToken"];

            if (!in_array($priority, $this->priorityList)) {
                $response = $this->sendError('Incorrect task priority. Avaliable priority list: {' . print_r($this->priorityList, true) . '}');
            } else {
                $res = TaskListModel::where("taskId", $taskId)->get()->toArray();

                if (count($res) > 0) {
                    //user cannot modify other tasks
                    if ($res[0]["userToken"] === $userToken) {
                        $result = TaskListModel::where("taskId", $taskId)->update(["priority" => $priority]);
                        
                        if ($result > 0) {
                            $response = $this->sendResponse([], 'Task Updated Successfully.');
                        } else {
                            $res = TaskListModel::where("taskId", $taskId)->where("userToken", $userToken)->get()->toArray();

                            if (!empty($res[0]["priority"]) && $res[0]["priority"] === $priority) {
                                $response = $this->sendError("Task with id {" . $taskId . "} already have priority {" . $priority . "}");
                            }
                        }
                    } else {
                        $response = $this->sendError("You cannot modify status of tasks other users");
                    }
                } else {
                    $response = $this->sendError("Task with id {" . $taskId . "} and userToken {" . $userToken . "} not founded...");
                }
            }
        }

        return $response;
    }

    /**
     * Update task title
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateTaskTitle(Request $request)
    {
        $result = 0;
        $input = $request->all();

        $validator = Validator::make($input, [
            'taskId' => 'required',
            'title' => 'required',
            'userToken' => 'required',
        ]);

        if ($validator->fails()){
            $response = $this->sendError('Validation Error.', $validator->errors());       
        } else {
            $taskId = $input["taskId"];
            $title = $input["title"];
            $userToken = $input["userToken"];

            if (strlen($title) > $this->titleMaxLenth) {
                $response = $this->sendError("Titles symbols count for tasks cannot be more {" .$this->titleMaxLenth . "} symbols");
            } else {
                $res = TaskListModel::where("taskId", $taskId)->get()->toArray();

                if (count($res) > 0) {
                    //user cannot modify other tasks
                    if ($res[0]["userToken"] === $userToken) {
                        $result = TaskListModel::where("taskId", $taskId)->update(["title" => $title]);
                        
                        if ($result > 0) {
                            $response = $this->sendResponse([], 'Task Updated Successfully.');
                        } else {
                            $res = TaskListModel::where("taskId", $taskId)->where("userToken", $userToken)->get()->toArray();
    
                            if (!empty($res[0]["title"]) && $res[0]["title"] === $title) {
                                $response = $this->sendError("Task with id {" . $taskId . "} already have title {" . $title . "}");
                            }
                        }
                    } else {
                        $response = $this->sendError("You cannot modify title of tasks other users");
                    }
                } else {
                    $response = $this->sendError("Task with id {" . $taskId . "} and userToken {" . $userToken . "} not founded...");
                }
            }
        }

        return $response;
    }

    /**
     * Update task description
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateTaskDescription(Request $request)
    {
        $result = 0;
        $input = $request->all();

        $validator = Validator::make($input, [
            'taskId' => 'required',
            'description' => 'required',
            'userToken' => 'required',
        ]);

        if ($validator->fails()){
            $response = $this->sendError('Validation Error.', $validator->errors());       
        } else {
            $taskId = $input["taskId"];
            $description = $input["description"];
            $userToken = $input["userToken"];

            if (strlen($description) > $this->descriptionMaxLenth) {
                $response = $this->sendError("Titles symbols count for tasks cannot be more {" .$this->descriptionMaxLenth . "} symbols");
            } else {
                $res = TaskListModel::where("taskId", $taskId)->get()->toArray();

                if (count($res) > 0) {
                    //user cannot modify other tasks
                    if ($res[0]["userToken"] === $userToken) {
                        $result = TaskListModel::where("taskId", $taskId)->update(["description" => $description]);
                        
                        if ($result > 0) {
                            $response = $this->sendResponse([], 'Task Updated Successfully.');
                        } else {
                            $res = TaskListModel::where("taskId", $taskId)->where("userToken", $userToken)->get()->toArray();
    
                            if (!empty($res[0]["description"]) && $res[0]["description"] === $description) {
                                $response = $this->sendError("Task with id {" . $taskId . "} already have description {" . $description . "}");
                            }
                        }
                    } else {
                        $response = $this->sendError("You cannot modify description of tasks other users");
                    }
                } else {
                    $response = $this->sendError("Task with id {" . $taskId . "} and userToken {" . $userToken . "} not founded...");
                }
            }
        }

        return $response;
    }

    /**
     * Update all avaliable params
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function multiUpdateTask(Request $request)
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'taskId' => 'required',
            'userToken' => 'required',
        ]);

        if ($validator->fails()){
            $response = $this->sendError('Validation Error.', $validator->errors());       
        } else {
            if (isset($input["taskStatus"])) {
                $response["taskStatus"] = $this->updateTaskStatus($request);
            }

            if (isset($input["priority"])) {
                $response["priority"] = $this->updateTaskPriority($request);
            }

            if (isset($input["title"])) {
                $response["title"] = $this->updateTaskTitle($request);
            }

            if (isset($input["description"])) {
                $response["description"] = $this->updateTaskDescription($request);
            }
        }

        return $response;
    }

    /**
     * Convert unixtime to datetime format
     *
     * @param int $unixtime
     * @param string $format
     * @return string $dateTime
     */
    
    private function unixtimeCorvert(int $unixtime, string $format): string
    {
        $dateTime = "";

        if (!empty($unixtime) && !empty($format)) {
            $dateTime = date($format, $unixtime);
        }

        return $dateTime;
    }

    /**
     * Get user subtasks
     *
     * @param int $taskId
     * @param int $taskId
     * @return \Illuminate\Database\Eloquent\Collection $subTasks
     */
    
     private function getUserSubTasks(int $taskId, string $userToken): \Illuminate\Database\Eloquent\Collection
     {
        $subTasks = [];

        if (!empty($taskId) ) {
            $subTasks = TaskListModel::where("parrentlyTaskId", $taskId)->where("userToken", $userToken)->get();

            foreach ($subTasks as $taskKey => $taskValue) {
                if (!empty($taskValue["createdAt"])) {
                    $subTasks[$taskKey]["createdAt"] = $this->unixtimeCorvert((int) $taskValue["createdAt"], "Y-m-d H:i:s");
                }
                if (!empty($taskValue["completedAt"])) {
                    $subTasks[$taskKey]["completedAt"] = $this->unixtimeCorvert((int) $taskValue["completedAt"], "Y-m-d H:i:s");
                }
            }
        }
 
         return $subTasks;
     }
}