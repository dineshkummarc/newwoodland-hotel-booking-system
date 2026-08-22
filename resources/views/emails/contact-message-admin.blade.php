<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #1c2a24;">
    <h2>New Contact Message</h2>
    <table cellpadding="6">
        <tr><td><strong>Name</strong></td><td>{{ $contactMessage->name }}</td></tr>
        <tr><td><strong>Email</strong></td><td>{{ $contactMessage->email }}</td></tr>
        <tr><td><strong>Phone</strong></td><td>{{ $contactMessage->phone ?: '—' }}</td></tr>
        <tr><td><strong>Subject</strong></td><td>{{ $contactMessage->subject ?: '—' }}</td></tr>
    </table>
    <p><strong>Message:</strong></p>
    <p>{{ $contactMessage->message }}</p>
</body>
</html>
