Hello <i>{{ $data->receiver }}</i>,
<p>{{ $data->person->full_name }} has left a new comment under the 
"{{ $data->post->title }}" post!
<br><i>{{ $data->comment->content }}</i></p>
<p>You are subscribed to this post updates. If you don't want to receive
such messages, you can unsubscribe by visiting 
<a href="{{ $data->path }}" target="_blank">this link</a></p> 
<b>© USOF</b>
