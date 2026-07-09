<?php

namespace App\Http\Controllers;

use App\Models\Reply;
use Illuminate\Http\Request;

class ReplyController extends Controller
{
    // If you already have a store method elsewhere, just add
    // 'QuotedReplyID' => $request->QuotedReplyID to that existing create() call.
    public function store(Request $request)
    {
        $data = $request->validate([
            'PostID' => 'required|exists:Post,PostID',
            'ReplyContent' => 'required|string|max:2000',
            'QuotedReplyID' => 'nullable|exists:Reply,ReplyID',
        ]);

        Reply::create([
            'PostID' => $data['PostID'],
            'UserID' => auth()->id(),
            'ReplyContent' => $data['ReplyContent'],
            'QuotedReplyID' => $data['QuotedReplyID'] ?? null,
        ]);

        return back()->with('status', 'Reply posted');
    }

    public function update(Request $request, Reply $reply)
    {
        abort_unless($reply->UserID === auth()->id(), 403);

        $data = $request->validate([
            'ReplyContent' => 'required|string|max:2000',
        ]);

        $reply->update(['ReplyContent' => $data['ReplyContent']]);

        return back()->with('status', 'Reply updated');
    }

    public function destroy(Reply $reply)
    {
        abort_unless($reply->UserID === auth()->id(), 403);

        $reply->delete();

        return back()->with('status', 'Reply deleted');
    }
}
