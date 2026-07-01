<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function download(Post $post)
    {
        if (!$post->Attachment) {
            abort(404);
        }

        return response()->download(Storage::disk('public')->path($post->Attachment), basename($post->Attachment));
    }
}
