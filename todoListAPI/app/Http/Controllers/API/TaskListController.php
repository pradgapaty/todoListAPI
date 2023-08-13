<?php

declare(strict_types = 1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TaskListModel;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Http\Resources\TaskListResources;
use Validator;

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
     * Show tasks by user token
     *
     * @param string $token
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTasksByToken(string $token, Request $request)
    {
        var_dump($request->all());
        $tasks = TaskListModel::where("userToken", $token)->limit(1000)->get();
        $result = $this->sendError('Any tasks with token {' . $token . '} not found.');

        if (count($tasks) > 0) {
            //convert dateTime for createdAt field
            foreach ($tasks as $taskKey => $taskValue) {
                if (!empty($taskValue["createdAt"])) {
                    $tasks[$taskKey]["createdAt"] = date("Y-m-d H:i:s", $taskValue["createdAt"]);
                }
            }

            $result = $this->sendResponse(TaskListResources::collection($tasks));
        }

        return $result;
    }

    /**
     * Add new task
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addNewTask(Request $request)
    {
        $taskId = 0;
        $input = $request->all();
        $message = "";

        //set token, when user used API firstly
        if (empty($input["userToken"])) {
            $input["userToken"] = md5(uniqid());
            $message = "Warning! For show, edit and delete yours tasks - save your token (identification): " . $input["userToken"];
        }

        //set default values
        $input["createdAt"] = time();
        $input["completedAt"] = 0;

        TaskListModel::create($input);
        $res = TaskListModel::where("userToken", $input["userToken"])->limit(1)->orderBy('createdAt', 'desc')->get()->toArray();

        if (!empty($res[0]["taskId"])) {
            $taskId = $res[0]["taskId"];
            $message = "Your taskId is {" . $taskId . "}";
        }

        $result = $this->sendResponse($message);

        return $result;
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
            $taskId = $input["taskId"];
            $taskStatus = $input["taskStatus"];
            $userToken = $input["userToken"];

            if (!in_array($taskStatus, $this->statusesList)) {
                $response = $this->sendError('Incorrect task status. Avaliable statuses: {' . print_r($this->statusesList, true) . '}');
            } else {
                $res = TaskListModel::where("taskId", $taskId)->get()->toArray();

                if (count($res) > 0) {
                    //additional function, user cannot change task status if status is Done (because after status is changed the he can delete task)
                    $statusinDb = $res[0]["status"];

                    if ($statusinDb === $this->getDoneTaskStatus()) {
                        $response = $this->sendError("User cannot change status. Task already Done");
                    } else {
                        //user cannot modify other tasks
                        if ($res[0]["userToken"] === $userToken) {
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
     * Remove the specified resource from storage.
     *
     * @param int $taskId
     * @return \Illuminate\Http\Response
     */
    public function deleteTask(string $taskId, string $userToken)
    {
        $result = 0;

        if (!empty($taskId)) {
            $resArr = TaskListModel::where("taskId", $taskId)->get()->toArray();

            if (count($resArr) > 0) {
                //user cannot delete other tasks
                if ($resArr[0]["userToken"] === $userToken) {
                    //user cannot delete task wit status "Done"
                
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
            'description' => 'description',
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
}