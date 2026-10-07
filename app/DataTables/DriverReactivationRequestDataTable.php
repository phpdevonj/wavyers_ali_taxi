<?php

namespace App\DataTables;

use App\Models\DriverReactivationRequest;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

use App\Traits\DataTableTrait;

class DriverReactivationRequestDataTable extends DataTable
{
    use DataTableTrait;
    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addColumn('driver_name', function ($query) {
                return $query->driver->display_name ?? __('message.not_available');
            })
            ->addColumn('contact_number', function ($query) {
                return $query->contact_number ?? '-';
            })
            ->editColumn('status', function ($query) {
                $status = 'warning';
                switch ($query->status) {
                    case 'reactivated':
                        $status = 'primary';
                        break;
                    case 'permanently_deleted':
                        $status = 'danger';
                        break;
                    case 'left_deactivated':
                        $status = 'dark';
                        break;
                }
                return '<span class="text-capitalize text-' . $status . ' badge badge-light-' . $status . '">' . str_replace('_', ' ', $query->status) . '</span>';
            })
            ->editColumn('created_at', function ($query) {
                return dateAgoFormate($query->created_at, true);
            })
            ->addIndexColumn()
            ->addColumn('action', 'driver-reactivation-request.action')
            ->order(function ($query) {
                if (request()->has('order')) {
                    $order = request()->order[0];
                    $column_index = $order['column'];

                    $column_name = 'created_at';
                    $direction = 'desc';
                    if ($column_index != 0) {
                        $column_name = request()->columns[$column_index]['data'];
                        $direction = $order['dir'];
                    }

                    $query->orderBy($column_name, $direction);
                }
            })
            ->rawColumns(['action', 'status']);
    }

    /**
     * Get query source of dataTable.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        $model = DriverReactivationRequest::with('driver')->orderBy('status', 'asc');

        return $this->applyScopes($model);
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns()
    {
        return [
            Column::make('DT_RowIndex')
                ->searchable(false)
                ->title(__('message.srno'))
                ->orderable(false)
                ->width(60),
            Column::computed('driver_name')->title(__('message.driver')),
            Column::computed('contact_number')->title(__('message.contact_number')),
            Column::make('status'),
            Column::make('created_at')->title(__('message.created_at')),
            Column::computed('action')
                  ->exportable(false)
                  ->printable(false)
                  ->width(120)
                  ->addClass('text-center'),
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename()
    {
        return 'DriverReactivationRequests_' . date('YmdHis');
    }
}
