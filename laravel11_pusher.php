<?php 

php artisan install:broadcasting   

composer require pusher/pusher-php-server       

php artisan make:migration create_posts_table     

php artisan make:model Post           

php artisan:make Controller PostController

php artisan make:event PostCreateNotification


npm install --save laravel-echo pusher-js

public function up()
{
    Schema::create('posts', function (Blueprint $table) {
        $table->id(); // Adds an auto-incrementing primary key column named 'id'
        $table->string('author'); // Creates a column for the author's name
        $table->string('title'); // Creates a column for the post's title
        $table->timestamps(); // Adds created_at and updated_at timestamp columns
    });
}

php artisan migrate

BROADCAST_DRIVER=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=ap2

VITE_APP_NAME="${APP_NAME}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"



// app/Event

	class PostCreateNotification implements ShouldBroadcastNow 
	{
		// public nofification send

		public $post

		public function __construct($post)
		{
			$this->$post = $post;
		}

		/**
	     * Get the data to broadcast.
	     *
	     * @return array
	     */
	    public function broadcastWith(): array
	    {
	        return $this->post;
	    }

		/**
	     * The event's broadcast name.
	     *
	     * @return string
	     */
		public function broadcastOn()
		{
			return 'test.notification';
		}
	}


	class AssinToStudent implements ShouldBroadcast 
	{

		// private nofification send
		public $message;
		public $user_id;

		public function __construct($message, $user_id)
		{
			$this->$message = $message;
			$this->$user_id = $user_id;
		}

		public function broadcastOn(): array
		{
			return [
				
				// new Channel('public-chat'), // public channel

				new PrivateChannel('private-chat.' .$this->$user_id), // private channel
			];
		}
	}


// controller

	public function store(Request $request)
    {
        // Validate the request
        $request->validate([
            'author' => 'required|string|max:255',
            'title' => 'required|string|max:255',
        ]);

        // Create the post
        $post = Post::create([
            'author' => $request->input('author'),
            'title' => $request->input('title'),
        ]);

        // Dispatch the event with the post data
        event(new PostCreateNotification([
            'author' => $post->author,
            'title' => $post->title,
        ]));

        // Redirect with success message
        return redirect()->back()->with('success', 'Post created successfully!');
    }


	public function assinToStudent(Request $request, $user_id)
    {
    	event(new AssinToStudent('message', $user_id));
    }

	Route::post('/posts', [PostController::class, 'store'])->name('posts.store');

// channel.php

	Broadcast::channel('App.Models.User.{id}', function ( $user, $id) {
		return (int)$user->id === (int) $id;
	})

	Broadcast::channel('private-chat.{userId}', function ( $user, $userId) {
		return (int)$user->id === (int) $userId;
	})




// resource/view/Post.blade.php

// create Post
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Post</title>
    <!-- Bootstrap CSS -->
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Create a New Post</h1>

        <!-- Display success message if available -->
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <!-- Display validation errors if any -->
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('posts.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="author">Author:</label>
                <input type="text" id="author" name="author" class="form-control" value="{{ old('author') }}" required>
            </div>

            <div class="form-group">
                <label for="title">Title:</label>
                <input type="text" id="title" name="title" class="form-control" value="{{ old('title') }}" required>
            </div>

            <button type="submit" class="btn btn-primary">Create Post</button>
        </form>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

// resource/view/footer.blade.php
// Show nofification 
<!DOCTYPE html>
<html lang="en">
	<head>
	    <meta charset="UTF-8">
	    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	    <title>Pusher Test with Icons</title>
	    <!-- Toastr CSS -->
	    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css">
	    <!-- Font Awesome CSS -->
	    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
	    <!-- jQuery -->
	    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
	    <!-- Toastr JavaScript -->
	    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>
	    <!-- Pusher JavaScript -->
	    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
	    <style>
	        /* Custom style for Toastr notifications */
	        .toast-info .toast-message {
	            display: flex;
	            align-items: center;
	        }
	        .toast-info .toast-message i {
	            margin-right: 10px;
	        }
	        .toast-info .toast-message .notification-content {
	            display: flex;
	            flex-direction: row;
	            align-items: center;
	        }
	    </style>
	    <script>
	        Pusher.logToConsole = true;

	        // Initialize Pusher
	        var pusher = new Pusher('b23d71886d55f985f153', {
	            cluster: 'ap2'
	        });

	        // Subscribe to the channel
	        var channel = pusher.subscribe('notification');

	        // Bind to the event
	        channel.bind('test.notification', function(data) {
	            console.log('Received data:', data); // Debugging line

	            // Display Toastr notification with icons and inline content
	            if (data.author && data.title) {
	                toastr.info(
	                    `<div class="notification-content">
	                        <i class="fas fa-user"></i> <span>   ${data.author}</span>
	                        <i class="fas fa-book" style="margin-left: 20px;"></i> <span>  ${data.title}</span>
	                    </div>`,
	                    'New Post Notification',
	                    {
	                        closeButton: true,
	                        progressBar: true,
	                        timeOut: 0, // Set timeOut to 0 to make it persist until closed
	                        extendedTimeOut: 0, // Ensure the notification stays open
	                        positionClass: 'toast-top-right',
	                        enableHtml: true
	                    }
	                );
	            } else {
	                console.error('Invalid data received:', data);
	            }
	        });

	        // Debugging line
	        pusher.connection.bind('connected', function() {
	            console.log('Pusher connected');
	        });
	    </script>
	</head>
	<body>
	    <h1>Pusher Test with Icons</h1>
	    <p>
	        Try publishing an event to channel <code>notification</code>
	        with event name <code>test.notification</code>.
	    </p>
	</body>
</html>


<script>

import Echo from 'laravel-echo';
window.Pusher = require('pusher-js');
window.Echo = new Echo({
  broadcaster: 'pusher',
  key: 'your_app_key',
  cluster: 'ap2',
  forceTLS: true,
  encrypted: true
});


setTimeout(() => { 
	window.Echo.channel('public-chat')
	.listen('AssinToStudent', (e) => {
		console.log(e.message)
	}); // public channel

	window.Echo.private('private-chat.{{Auth::user->id}}')
	.listen('AssinToStudent', (e) => {
		console.log(e.message)
	}); // private channel
	
}, 200);
</script>



----------------------------REVERB------------------------------
composer require laravel/reverb
php artisan reverb:install

BROADCAST_DRIVER=reverb

REVERB_APP_ID=posapp
REVERB_APP_KEY=poskey
REVERB_APP_SECRET=possecret
REVERB_HOST=localhost
REVERB_PORT=8080


npm install laravel-echo
npm install pusher-js

php artisan reverb:start
php artisan reverb:start --host=0.0.0.0 --port=8080


php artisan make:event SaleCreated
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class SaleCreated implements ShouldBroadcast
{
    public $sale;

    public function __construct($sale)
    {
        $this->sale = $sale;
    }

    public function broadcastOn()
    {
        return new Channel('sales');
    }
}


Broadcast::channel('managment.{userId}', function ( $user, $userId) {
		return (int)$user->id === (int) $userId;
	})





