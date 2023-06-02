<?php

namespace App\Http\Controllers\Chat;

use App\Events\MessageEvent;
use App\Events\RealTimeMessage;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Room;
use App\Models\RoomUsers;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function index(){
        // event(new RealTimeMessage('Hello World'));
        $users = User::where('id', '!=', Auth::id())->where('status', 1)->get();
        return view('chat.index', compact('users'));
    }

    public function check_room($user_id){
        $auth_user_rooms_ids = auth()->user()->room_users()->pluck('room_id');
        $check_room = RoomUsers::where('user_id', $user_id)->whereIn('room_id', $auth_user_rooms_ids)->first();
        if($check_room == null){
            $room = Room::create();
            $room_id = $room->id;
            auth()->user()->room_users()->create([
                'room_id' => $room->id,
            ]);

            RoomUsers::create([
                        'room_id' => $room->id,
                        'user_id' => $user_id
                    ]);
        }
        else{
            $room_id = $check_room->room_id;
        }

        return redirect()->route('room', $room_id);
    }

    public function room(Room $room, $id)
    {
        $user = auth()->user();
        $users = User::where('id', '!=', Auth::id())->where('status', 1)->get();
        $messages = ChatMessage::where('room_id', $id)->get();
        $room = Room::find($id);

        // broadcast(new MessageEvent($id, $messages))->toOthers();
        return view('chat.room', compact('room', 'messages', 'users'));
    }


    public function message_store(Request $request, $id)
    {
// dd( $request->all());
        $user = auth()->user();
        $room = Room::find($id);
        $roommate = RoomUsers::where('user_id', '!=', $user->id)->where('room_id', $room->id)->first();

        $message = $room->messages()->create([
            'user_id' => $user->id,
            'to_user_id' => $roommate->user_id,
            'content' => $request->content,
        ]);
        // $message = ChatMessage::create([
        //     'user_id' => $user->id,
        //     'to_user_id' => $roommate->user_id,
        //     'room_id' => $id,
        //     // 'content' => $request->content,
        // ]);
        if ($request->has('file')) {

            $path_file = FileUploadService::upload($request->file, 'chat-files/' . $room->id);
            $message->file = $path_file;
            $message->save();
        }

        $message = ChatMessage::where('id',$message->id)->with('user.roles')->first();

        // broadcast(new MessageEvent($room->id, $message))->toOthers();
        event(new MessageEvent($room->id, $message));

        return response()->json(['result' => 1], 200);
    }
}
