#!/bin/bash
php artisan tinker --execute="
\Webkul\Lead\Models\Stage::orderBy('lead_pipeline_id')->get(['id','lead_pipeline_id','code','name'])->each(function(\$s){ echo \$s->lead_pipeline_id.' | '.\$s->id.' | '.\$s->code.' | '.\$s->name.PHP_EOL; });
"
