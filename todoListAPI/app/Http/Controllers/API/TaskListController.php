<?php

declare(strict_types = 1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TaskListModel;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Http\Resources\TaskListResources;

class TaskListController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $tasks = TaskListModel::all();
        return $this->sendResponse(TaskListResources::collection($tasks));
    }

    /**
     * Show tasks by user token
     *
     * @param string $token
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTasksByToken(string $token)
    {
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
        $validator = Validator::make($input, [
            // 'title' => 'required',
            // 'description' => 'required',
            // 'priority' => 'required'
        ]);

        //set token, when user used API firstly
        if (empty($input["userToken"])) {
            $input["userToken"] = md5(uniqid());
            $message = "Warning! For show, edit and delete yours tasks - save your token (identification): " . $input["userToken"];
        }

        //set default values
        $input["createdAt"] = time();
        $input["completedAt"] = 0;

        if($validator->fails()){
            $result = $this->sendError('Validation Error.', $validator->errors());       
        } else {
            TaskListModel::create($input);
            $res = TaskListModel::where("userToken", $input["userToken"])->limit(1)->orderBy('createdAt', 'desc')->get()->toArray();

            if (!empty($res[0]["taskId"])) {
                $taskId = $res[0]["taskId"];
                $message = "Your taskId is {" . $taskId . "}";
            }

            $result = $this->sendResponse($message);
        }

        return $result;
    }

    // /**
    //  * Show the form for editing the specified resource.
    //  *
    //  * @param  int  $id
    //  * @return \Illuminate\Http\Response
    //  */
    // public function edit($id)
    // {
    //     //
    // }
    // /**
    //  * Update the specified resource in storage.
    //  *
    //  * @param  \Illuminate\Http\Request  $request
    //  * @param  int  $id
    //  * @return \Illuminate\Http\Response
    //  */
    // public function update(Request $request, $id)
    // {
    //     $input = $request->all();
   
    //     $validator = Validator::make($input, [
    //         'name' => 'required',
    //         'detail' => 'required'
    //     ]);
   
    //     if($validator->fails()){
    //         return $this->sendError('Validation Error.', $validator->errors());       
    //     }
    //     $product = TaskList::find($id);   
    //     $product->name = $input['name'];
    //     $product->detail = $input['detail'];
    //     $product->save();
   
    //     return $this->sendResponse(new ProductResource($product), 'Product Updated Successfully.');
    // }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $taskId
     * @return \Illuminate\Http\Response
     */
    public function deleteTaskById(string $taskId)
    {
        $result = 0;

        if (!empty($taskId)) {
            $res = TaskListModel::where("taskId", $taskId)->get();

            if (count($res) > 0) {
                $taskForDelete = TaskListModel::where("taskId", $taskId);
                $result = $taskForDelete->delete();
            } else {
                $response = $this->sendError("Task with id {" . $taskId . "} not founded...");
            }
        } else {
            $response = $this->sendError('Empty task id');
        }

        if ($result > 0) {
            $response = $this->sendResponse([], 'Product Deleted Successfully.');
        }

        return $response;
    }
}