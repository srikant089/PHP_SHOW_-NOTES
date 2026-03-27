<?php
composer create-project laravel/laravel agora
cd agora
php artisan make:auth
composer require agora/agora-access-token


composer require pusher/pusher-php-server
npm install --save laravel-echo pusher-js
npm install agora-rtc-sdk-ng

Schema::create('messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sender_id');
    $table->foreignId('receiver_id');
    $table->text('message');
    $table->boolean('seen')->default(false);
    $table->timestamps();
});

Schema::create('calls', function (Blueprint $table) {
    $table->id();
    $table->foreignId('caller_id');
    $table->foreignId('receiver_id');
    $table->string('channel_name');
    $table->string('type'); // voice or video
    $table->string('status'); // calling, accepted, rejected, ended
    $table->timestamps();
});

//Event
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    public $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('chat.'.$this->message->receiver_id);
    }
    public function broadcastAs()
    {
        return 'MessageSent';
    }
}

class MessageDelivered implements ShouldBroadcast
{
    public $messageId;

    public function __construct($messageId)
    {
        $this->messageId = $messageId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('chat-status.' . auth()->id());
    }

    public function broadcastAs()
    {
        return 'MessageDelivered';
    }
}

class MessageRead implements ShouldBroadcast
{
    public $messageId;

    public function __construct($messageId)
    {
        $this->messageId = $messageId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('chat-status.' . auth()->id());
    }

    public function broadcastAs()
    {
        return 'MessageRead';
    }
}

class TypingEvent implements ShouldBroadcast
{
    public $senderId;

    public function __construct($senderId)
    {
        $this->senderId = $senderId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('typing.' . $this->senderId);
    }

    public function broadcastAs()
    {
        return 'TypingStarted';
    }
}

class UserOnline implements ShouldBroadcast
{
    public $userId;

    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    public function broadcastOn()
    {
        return new Channel('online-users');
    }

    public function broadcastAs()
    {
        return 'UserOnline';
    }
}

<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class IncomingCall implements ShouldBroadcast
{
    use SerializesModels;

    public $call;

    public function __construct(Call $call)
    {
        $this->call = $call;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('call.' . $this->call->receiver_id);
    }

    public function broadcastAs()
    {
        return 'IncomingCall';
    }
}

class CallAccepted implements ShouldBroadcast
{
    use SerializesModels;

    public $call;

    public function __construct(Call $call)
    {
        $this->call = $call;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('call.' . $this->call->caller_id);
    }

    public function broadcastAs()
    {
        return 'CallAccepted';
    }
}

class CallRejected implements ShouldBroadcast
{
    public $call;

    public function __construct($call)
    {
        $this->call = $call;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('call.'.$this->call->caller_id);
    }

    public function broadcastAs()
    {
        return 'CallRejected';
    }
}

class CallEnded implements ShouldBroadcast
{
    use SerializesModels;

    public $call;

    public function __construct(Call $call)
    {
        $this->call = $call;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('call.' . $this->call->caller_id),
            new PrivateChannel('call.' . $this->call->receiver_id),
        ];
    }

    public function broadcastAs()
    {
        return 'CallEnded';
    }
}



// ------------------------Controller-----------------------------
//ChatController:
use App\Events\MessageSent;
use App\Models\Message;

public function users()
{
    return User::where('id','!=',auth()->id())->get();
}

public function sendMessage(Request $request)
{
    $message = Message::create([
        'sender_id'=>auth()->id(),
        'receiver_id'=>$request->receiver_id,
        'message'=>$request->message
    ]);

    broadcast(new MessageSent($message))->toOthers();

    return $message;
}

public function receiveMessages($userId)
{
    $messages = Message::where(function($q) use ($userId){
        $q->where('sender_id', auth()->id())
          ->where('receiver_id', $userId);
    })
    ->orWhere(function($q) use ($userId){
        $q->where('sender_id', $userId)
          ->where('receiver_id', auth()->id());
    })
    ->orderBy('created_at')
    ->get();

    broadcast(new MessageDelivered(auth()->id(), $request->receiver_id));

    return response()->json($messages);
}

public function typing($request)
{
	broadcast(new TypingEvent(auth()->id(), $request->receiver_id));
}


//CallController:
use App\Events\IncomingCall;
use App\Events\CallAccepted;
use App\Events\CallRejected;
use App\Events\CallEnded;
use App\Models\Call;

public function startCall(Request $request)
{
	$channel = "call_".uniqid();

	$call = Call::create([
		'caller_id'=>auth()->id(),
		'receiver_id'=>$request->receiver_id,
		'channel_name'=>$channel,
		'type'=>$request->type,
		'status'=>"calling"
	]);

	broadcast(new IncomingCall($call))->toOthers();

	return $call;
}

public function acceptCall($id)
{
	$call = Call::find($id);
	$call->status="accepted";
	$call->save();
	broadcast(new CallAccepted($call));
	return $call;
}

public function rejectCall($id)
{
	$call = Call::find($id);
	$call->status="rejected";
	$call->save();
	broadcast(new CallRejected($call));
}

public function endCall($id)
{
    $call = Call::findOrFail($id);
    $call->status = "ended";
    $call->save();
    broadcast(new CallEnded($call))->toOthers();
    return response()->json([
        "status" => "call ended"
    ]);
}

//routes/web.php
Route::post('/send-message',[ChatController::class,'sendMessage']);
Route::get('/messages/{user}',[ChatController::class,'receiveMessages']);

Route::post('/call/start', [CallController::class,'startCall']);
Route::post('/call/accept/{id}', [CallController::class,'acceptCall']);
Route::post('/call/reject/{id}', [CallController::class,'rejectCall']);
Route::post('/call/end/{id}', [CallController::class,'endCall']);


//routes/channels.php
Broadcast::channel('call.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// ---------------------blade--------------------
//chat.blade
<ul id="userList"></ul>

<ul id="chat"></ul>
<input type="text" id="message">
<button onclick="sendMessage()">Send</button>



<script>
	fetch('/users')
	.then(res=>res.json())
	.then(users=>{
	 users.forEach(u=>{
	  let li=document.createElement("li");
	  li.innerText=u.name;
	  document.getElementById("userList").appendChild(li);
	 });
	});


	function sendMessage(){
	 	fetch('/send-message',{
			method:'POST',
			headers:{
			 'Content-Type':'application/json',
			 'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
			},
			body:JSON.stringify({
			 receiver_id:receiverId,
			 message:document.getElementById("message").value
			})
		});
	}


	Echo.private(`chat.${userId}`)
	.listen('.MessageSent', (e) => {
	    console.log("New message:", e.message);
	    let chat = document.getElementById("chat");
	    let li = document.createElement("li");
	    li.innerText = e.message.message;
	    chat.appendChild(li);
	});


	Echo.private(`typing.${userId}`)
	.listen("TypingEvent",(e)=>{
	 document.getElementById("typing").innerText="Typing...";
	});

	Echo.private(`typing.${userId}`)
	.listen('.TypingStopped', ()=>{
	   hideTypingIndicator()
	})

	Echo.private(`chat-status.${userId}`)
	.listen('.MessageDelivered', (e)=>{
	   markDelivered(e.messageId)
	})

	Echo.private(`chat-status.${userId}`)
	.listen('.MessageRead', (e)=>{
	   markRead(e.messageId)
	})

</script>

//call.blade
<button onclick="startCall(2)">Audio Call</button>

<div id="incomingCall" style="display:none">
    <p>Incoming Call...</p>
    <button onclick="acceptCall()">Accept</button>
    <button onclick="rejectCall()">Reject</button>
</div>

<button onclick="endCall()">End Call</button>



//Footer 
<script src="https://js.pusher.com/8.2/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo/dist/echo.iife.js"></script>
<script type="module">

	import AgoraRTC from "agora-rtc-sdk-ng";

	const echo = new Echo({
	 broadcaster: 'pusher',
	 key: 'your_key',
	 cluster: 'ap2',
	 forceTLS: true
	});

	//Listen on IncomingCall other Side
	Echo.private(`call.${userId}`)
	.listen('.IncomingCall', (e) => {
		console.log("Incoming call", e);
		window.currentCall = e.call;
		document.getElementById("incomingCall").style.display = "block";
		document.getElementById("callerName").innerText =
		"Incoming " + e.call.type + " call";
	});

	// Listen on Caller Side
	Echo.private(`call.${userId}`)
		.listen('.CallAccepted', (e) => {
		console.log("Call accepted", e);
		joinAgora(e.call.channel_name);

	});

	//Call End
	Echo.private(`call.${userId}`)
	.listen('.CallEnded', (e) => {
	    console.log("Call ended");
	    leaveAgora();
	    document.getElementById("callUI").style.display = "none";

	});


	async function startAgoraAudio(){
		const APP_ID = "YOUR_AGORA_APP_ID";
		const CHANNEL = "test";
		const client = AgoraRTC.createClient({mode:'rtc',codec:'vp8'});
		await client.join(APP_ID,CHANNEL_NAME,TOKEN,null);
		const mic = await AgoraRTC.createMicrophoneAudioTrack();
		await client.publish([mic]);
	}

	async function leaveAgora(){
		try {

			if(localAudioTrack){
				localAudioTrack.stop();
				localAudioTrack.close();
			}

			if(client){
				await client.leave();
			}

			console.log("Left Agora channel");

		}catch(error){
			console.error("Leave error:", error);
		}
	}

	//Join video Agora Channel
	async function joinAgora(channel) {
		const res = await fetch(`/agora/token?channel=${channel}`);
		const data = await res.json();
		await client.join(APP_ID, channel, data.token, null);
		const tracks = await AgoraRTC.createMicrophoneAndCameraTracks();
		tracks[1].play("local-video");
		await client.publish(tracks);
	}

	//Leave Agora Call
	async function leaveAgoraChannel(){
	    await client.leave();
	    console.log("Left call");
	}


	//startCall 
	let currentCall=null;
	function startCall(receiverId){
		fetch('/call/start',{
			method:'POST',
			headers:{
				'Content-Type':'application/json',
				'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
			},
			body:JSON.stringify({receiver_id:receiverId})
		})
		.then(res=>res.json())
		.then(data=>{
			currentCall=data;
			console.log("calling...");
		})
	}


	//AcceptCall
	function acceptCall(){
		fetch(`/call/accept/${currentCall.id}`,{
			method:'POST',
			headers:{
			'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
			}
		});
		startAgoraAudio();
	}

	//Reject Call
	function rejectCall(){
		fetch(`/call/reject/${currentCall.id}`,{
			method:'POST',
			headers:{
			'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
			}
		});
		document.getElementById("incomingCall").style.display="none";
	}

	//Call End
	function endCall(){
		fetch(`/call/end/${currentCall.id}`,{
			method:"POST",
			headers:{
			'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
			}
		});
		leaveAgora();
	}

</script>

-----------------------video-------------------------

user_meetings
user_id, 
app_id, 
token, 
app_certificate, 
channel, 
url,
uid,
event

meeting_entries
user_id, 
random_user,
name varchar 20
url varchar 20
status int 20 0
channel,
event


//Route

Route::get('/joinMeeting/{url?}' [MeetingController::class, joinMeeting]->name('joinMeeting'));

Route::get('/' [MeetingController::class, userMeeting]->name('userMeeting'));

Route::post('/createMeeting' [MeetingController::class, createMeeting]->name('createMeeting'));

Route::post('/saveUserName' [MeetingController::class, saveUserName]->name('saveUserName'));

Route::post('/meetingApprove' [MeetingController::class, meetingApprove]->name('meetingApprove'));



app/Common/helper.php

//composer.json add file key files and file path under the autoload object
// "autoload" : {
	"psr-4" : {},
	"files": ["app/Common/helper.php"]
}

function createAgoraProject($name) {

	$customerKey= env('customerKey');
	$customerSecret= env('customerSecret');

	$credentials = $customerKey . ':' .  $customerSecret;

	// Encode with base64
	$base64Credentials = base64_encode($credentials);

	// Create authorization header
	$arr_header = "Authorization: Basic " . $base64Credentials;

	// Set the API URL for checking users in the channel
	$url = "https://api.agora.io/dev/v1/project";

	$curl = curl_init();

	// Send HTTP request
	curl_setopt_array($curl, array(
	    CURLOPT_URL => $url,
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_ENCODING => '',
	    CURLOPT_MAXREDIRS => 10,
	    CURLOPT_TIMEOUT => 0,
	    CURLOPT_FOLLOWLOCATION => true,
	    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	    CURLOPT_CUSTOMREQUEST => 'POST',
	    CURLOPT_POSTFIELDS => '{
	    	"name" : "' . $name . '",
	    	"enable_sign_key" : true
	    }'

	    CURLOPT_HTTPHEADER => array(
	        $arr_header,
	        'Content-Type: application/json'
	    ),
	));

	// Execute the request
	$response = curl_exec($curl);

	// Close cURL
	curl_close($curl);


	// Decode the JSON response
	return  $result = json_decode($response, true);

}


function createToken($appId, $appCertifcate, $channelName) {

	
	// Set the API URL for checking users in the channel
	$url = "https://mehandrucompany.com/agoraToken/sample/RtcTokenBuilderSample.php";

	$curl = curl_init();

	// Send HTTP request
	curl_setopt_array($curl, array(
	    CURLOPT_URL => $url,
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_ENCODING => '',
	    CURLOPT_MAXREDIRS => 10,
	    CURLOPT_TIMEOUT => 0,
	    CURLOPT_FOLLOWLOCATION => true,
	    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	    CURLOPT_CUSTOMREQUEST => 'POST',
	    CURLOPT_POSTFIELDS => array(
	    	'appId' => $appId, 
	    	'appCertifcate' => $appCertifcate, 
	    	'channelName' =>$channelName
	    )
	));

	// Execute the request
	$response = curl_exec($curl);

	// Close cURL
	curl_close($curl);
	return  $response;
}

function generateRandomString($length = 7) {

	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPRSTUVWXYZ';
	$characterLenght = strlen($characters);
	$randomString = '';

	for($i = 0; $i< $length; $i++) {
		$randomString .= $characters[rand(0, $characterLenght -1)];
	}

	return $randomString;
}







User Model
public function getUserMeetingInfo() {
	return $this->hasOne(UserMeeting::class, 'user_id', 'id');
}




MeetingController
use App\Events\SendNotificaion;

public function createMeeting() {

	$meeting = Auth:User()->getUserMeetingInfo()->first();


	if(!isset($meeting->id)) {
		$name = 'agora'. rand(11111,99999);
		$meetingDate = createAgoraProject($name);

		if(isset($meetingDate->project->id)) {
			$meeting = new UserMeeting();
			$meeting->user_id  = Auth::User()->id;
			$meeting->app_id   = $meetingDate->project->vendor_key;
			$meeting->app_certificate = $meetingDate->project->sign_key;
			$meeting->channel  = $meetingDate->project->name;
			$meeting->uid     = rand(11111,99999);
			$meeting->save();

		}else {
			echo "Project not Created";
		}
	}

	$token = createToken($meeting->app_id, $meeting->app_certificate, $meeting->channel);

	$meeting->token     = $token;
	$meeting->url     = generateRandomString();
	$meeting->event     = generateRandomString(5);
	$meeting->save();

	if(Auth::User()->id == $meeting->ueser_id) {
			Session::put('meeting', $meeting->url);
	}

	return redirect('joinMeeting/$meeting->url');


}

public function joinMeeting($url ='') {

	$meeting = UserMeeting::where('url', $url)->first();

	if (isset($meeting->id)) { 

		$meeting->app_id = trim($meeting->app_id);
		$meeting->app_certificate = trim($meeting->app_certificate);
		$meeting->channel = trim($meeting->channel);
		$meeting->token = trim($meeting->token);
		$meeting->event = trim($meeting->event);

		if(Auth::User() && Auth::User()->id == $meeting->user_id ) { 
			$channel = $meeting->channel;
			$event = $meeting->event;
		} else {

			$channel = $meeting->channel;
			$event = generateRandomString(5);

			if(!Auth::User()) {
				$random_user = rand(111111, 999999);
				Session::put('random_user', $random_user);

				$this->createEntry($meeting->user_id, $random_user, $meeting->url,$event,$channel);
			} else {
				Session::put('random_user', Auth::User()->id);

				$this->createEntry($meeting->user_id, Auth::User()->id, $meeting->url,$event,$channel);
			}

			return view('joinUser', get_defined_vars());
		}

	} else {

	}
}

public function createEntry($user_id, $random_user, $url, $event, $channel) {
	$entry = new MeetingEntry();
	$entry->user_id = $user_id;
	$entry->random_user = $random_user;
	$entry->url = $url;
	$entry->status = 0;
	$entry->event = $event;
	$entry->channel = $channel;
	$entry->save();
}


public function saveUserName(Request $request) {
	$saveNaame = MeetingEntery::where(['random_user' => $request->random_user, 'url' => $request->url])->first();

	if ($saveNaame->status == 3) {
		// Host reject video call
	} else {
		$saveNaame->name = $request->name;
		$saveNaame->status = 1;
		$saveNaame->save();

		$meeting->channel,$meeting->event = UserMeeting::where('url', $request->url)->first();

		$data = [
			'random_user' => $request->random_user,
			'title'=> $request->name .' wants to enter in the meeting'
		];

		event( new SendNotificaion($data, $meeting->channel,$meeting->event));
	}
}

public function meetingApprove (Request $request) {
	$saveNaame = MeetingEntery::where(['random_user' => $request->random_user, 'url' => $request->url])->first();

	$saveNaame->status = $request->type;
	$saveNaame->save();

	$data = [
		'status' => $request->type
	];

	event( new SendNotificaion($data, $saveNaame->channel,$saveNaame->event));

}

------------------------------------------------

//app/event

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class SendNotificaion implements ShouldBroadcast
{
    public $data;
    public $channel;
    public $event;

    public function __construct($data, $channel,  $event)
    {
        $this->data = $data;
        $this->channel = $channel;
        $this->event = $event;
    }

    public function broadcastOn()
    {
        return [$this->channel];
    }

    public function broadcastAs()
    {
        return $this->event;
    }
}



==================Home================

<input type="text" id="linkUrl" value="" palceholder="enter link">

<button id="copy">CopyLink</button>
<button id="join-btn1" onclick="joinUserMeeting()">Join meeting</button>


<script>
	function joinUserMeeting() {

		var link = $('#linkUrl').val();
		if(link.trim() == '' || link.lenght < 1) {
			alert('Please Enter the link')
			return;
		} else {
			window.location.href = link;
		}
	}

</script>


==================joinUser================

<link rel="stylesheet" type="text/css" media="screen" href="{{asset{{asset('agoraVideo/main.css')}}">

<body>

@if(!session()->has('meeting'))
	<input type='text' id='linkname' value="">
@endif 

<input type='text' id='linkUrl' value="{{url('joinMeeting')}}/{{$meeting->url}}">
<button  id='join-btn' style='display:none'></button>
<button  id='join-btn2'>Join Stream</button>
<button  id='join-btns' onclick='copyLink()'>Copy Link</button>

<------------------Meeting Instance--------------------->
<div id="stream-wrapper" style="height:100%; display:block">
	<div id="video-stream"></div>
	<div id="stream-controls">
		<button id="leave-btn"> Leave Stream</button>
		<button id="mic-btn"> Mic on</button>
		<button id="camera-btn">Carmera on</button>
		<!-- <button id="rec-btn">Rec off</button>-->
	</div>
</div>

<input id='appid' type='hidden' value="{{$meeting->app_id}}" readonly>
<input id='token' type='hidden' value="{{$meeting->token}}" readonly>
<input id='channel' type='hidden' value="{{$meeting->channel}}" readonly>
<input id='event' type='hidden' value="{{event}}" readonly>
<input id='urlId' type='hidden' value="{{$meeting->url}}" readonly>

<input id='timer' type='hidden' value="">
<input id='user_meeting' type='hidden' value="0">
<input id='user_permission' type='hidden' value="0">
</body>
<script src="{{asset('agoraVideo/AgoraRTC_N-4.7.3.js')}}">
<script src="{{asset('agoraVideo/main.js')}}">
<script src="https://js.pusher.com/8.2/pusher.min.js"></script>
<script>
	//Pusher web socket initalies 
	var nofificationChannel= $('#channel').val();
	var nofificationEvent= $('#event').val();

	// Enable pusher loggin -don't include on live
	Pusher.logToConsole= true;

	var pusher = new Pusher('', { cluster: 'ap2'});

	var channel = pusher.subscribe(nofificationChannel);

	channel.bind(nofificationEvent, function(dtat) {

		alert(data.data.title);
		//alert(JSON.stringify(data));

		@if(session()->has('meeting')) {
			// Host User
			if(confirm(data.data.title)) {
				meetingApprove(data.data.random_user, 2);
			}else{
				meetingApprove(random_user, 3);
			}
		@else 
			// Join User
			if(data.data.status ==2) {
				// Meeting Start
				$('#join_btn').click();
				document.getElementById('stream-controls').style.display='flex';
			} else if(data.data.status ==3) {
				// Meeting entry denied by host
				alert('Host has been denied your entry');
			}
		@endif
	});
</script>
	

<script>

	// copyLink
	function copyLink() {
		var urlPage = window.location.href;
		var temp = $('<input>');
		$('body').append(temp);
		temp.val(urlPage).select();
		document.execCommand('copy');
		temp.remove();
		$('#join-btns').text('URL COPIED');
	}


	//Host User
	$('#join-btn2').click(function () {
		@if(session()->has('meeting')) 
			$('#join-btn2').click();
			document.getElementById('stream-controls').style.display='flex';
		@else
			//join user
			var name = $('#linkName').val();
			if(name == '' || name.length < 1) {
				alert("Enter your name");
				return;
			}else {
				saveUserName(name);
				alert('Request has been sent to Host, Please wait..')
			}
		@endif
	})

	function saveUserName(name) {
		var url = "{{url('saveUserName')}}";
		var random_user = "{{session()->get('random_user')}}";
		var urlId = $('#urlId').val();
		$.ajax({
			url: url,
			headers: {
				'X-CSRF-TOKEN' : '{{ csrf_token()}}'
			},
			data:{
				'url': urlId,
				'name': name,
				'random_user': random_user
			},
			type: 'POST',
			success: function (result) {
			}
		})
	}


	function meetingApprove(random_user, type) {
		var url = "{{url('meetingApprove')}}";
		var urlId = $('#urlId').val();
		$.ajax({
			url: url,
			headers: {
				'X-CSRF-TOKEN' : '{{ csrf_token()}}'
			},
			data:{
				'url': urlId,
				'type': type,
				'random_user': random_user
			},
			type: 'POST',
			success: function (result) {
			}
		})
	}
</script>
<script type="module">

import AgoraRTC from "agora-rtc-sdk-ng";

const client = AgoraRTC.createClient({mode:"rtc", codec:"vp8"});

const APP_ID = "YOUR_AGORA_APP_ID";
const CHANNEL = "test";

async function startCall(){

 const res = await fetch(`/agora/token?channel=${CHANNEL}`);
 const data = await res.json();

 await client.join(APP_ID, CHANNEL, data.token, null);

 const tracks = await AgoraRTC.createMicrophoneAndCameraTracks();

 tracks[1].play("local-player");

 await client.publish(tracks);

}

client.on("user-published", async (user, mediaType) => {

 await client.subscribe(user, mediaType);

 if(mediaType === "video"){
   user.videoTrack.play("remote-player");
 }

 if(mediaType === "audio"){
   user.audioTrack.play();
 }

});

</script>


//Controller
Route::get('/agora/token',[AgoraController::class,'token']);
use Agora\RtcTokenBuilder;

class AgoraController extends Controller
{
    public function token(Request $request)
    {
        $appID = env('AGORA_APP_ID');
        $appCertificate = env('AGORA_APP_CERTIFICATE');

        $channelName = $request->channel;
        $uid = 0;
        $role = RtcTokenBuilder::RolePublisher;
        $expireTime = 3600;

        $currentTimestamp = now()->timestamp;
        $privilegeExpiredTs = $currentTimestamp + $expireTime;

        $token = RtcTokenBuilder::buildTokenWithUid(
            $appID,
            $appCertificate,
            $channelName,
            $uid,
            $role,
            $privilegeExpiredTs
        );

        return response()->json(['token'=>$token]);
    }
}



// Blade UI (Call Page) call.blade.php

//startCall Call UI
<div id="local-player"></div>
<div id="remote-player"></div>
<button onclick="startCall()">Start Call</button>


//Incoming Call UI
<div id="incomingCall" style="display:none">
<p>Incoming Call</p>
<button onclick="acceptCall()">Accept</button>
<button onclick="rejectCall()">Reject</button>
</div>



<script type="module">

import AgoraRTC from "agora-rtc-sdk-ng";

const client = AgoraRTC.createClient({mode:"rtc", codec:"vp8"});

const APP_ID = "YOUR_AGORA_APP_ID";
const CHANNEL = "test";

async function startCall(){

 const res = await fetch(`/agora/token?channel=${CHANNEL}`);
 const data = await res.json();

 await client.join(APP_ID, CHANNEL, data.token, null);

 const tracks = await AgoraRTC.createMicrophoneAndCameraTracks();

 tracks[1].play("local-player");

 await client.publish(tracks);

}

client.on("user-published", async (user, mediaType) => {

 await client.subscribe(user, mediaType);

 if(mediaType === "video"){
   user.videoTrack.play("remote-player");
 }

 if(mediaType === "audio"){
   user.audioTrack.play();
 }

});

// joinCall
async function joinCall(channel,token){
	await client.join(APP_ID, channel, token, null);
	const tracks = await AgoraRTC.createMicrophoneAndCameraTracks();
	tracks[1].play("local-video");
	await client.publish(tracks);

}



Echo.private(`call.${userId}`)
.listen("IncomingCall",(e)=>{

window.callData = e.call;

document.getElementById("incomingCall").style.display="block";

});

</script>
 
