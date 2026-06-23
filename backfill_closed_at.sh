#!/bin/bash
php artisan tinker --execute="
\$wonCodes = ['won', 'pedido-entregado'];
\$lostCodes = ['lost', 'pedidos-cancelado'];
\$stageIds = \Webkul\Lead\Models\Stage::whereIn('code', array_merge(\$wonCodes, \$lostCodes))->pluck('id');
\$count = \Webkul\Lead\Models\Lead::whereIn('lead_pipeline_stage_id', \$stageIds)->whereNull('closed_at')->count();
echo 'Leads a corregir: '.\$count.PHP_EOL;
\Webkul\Lead\Models\Lead::whereIn('lead_pipeline_stage_id', \$stageIds)->whereNull('closed_at')->update(['closed_at' => \DB::raw('updated_at')]);
echo 'Listo.'.PHP_EOL;
"
