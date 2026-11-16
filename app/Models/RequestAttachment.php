<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file on a request, kept on the private disk and served only through the app.
 */
class RequestAttachment extends Model
{
    public const KIND_SUPPORTING = 'supporting';

    public const KIND_COMPLETION = 'completion';

    /** Validation rule for one uploaded file: 5 MB, common document and image types. */
    public const FILE_RULE = 'file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx';

    protected $table = 'request_attachments';

    protected $fillable = ['request_id', 'uploaded_by', 'kind', 'original_name', 'path', 'mime_type', 'size'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SchoolRequest::class, 'request_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function readableSize(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1).' MB'
            : number_format(max(1, $this->size / 1024)).' KB';
    }
}
