<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class FeedbackController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->validate([
            'feedbackMessage' => 'required|string|max:2000',
            'feedbackEmail' => 'nullable|email',
            'feedbackName' => 'nullable|string',
        ]);

        Mail::send('emails.feedback', $data, function ($mail) use ($data) {
            $mail->to(config('mail.manager_mail') ?? "aschuster.development@outlook.de")
                 ->subject('Neues Feedback');

            if (!empty($data['feedbackEmail'])) {
                $mail->replyTo($data['feedbackEmail']);
            }
        });

        return response()->json(['success' => true]);
    }
}
