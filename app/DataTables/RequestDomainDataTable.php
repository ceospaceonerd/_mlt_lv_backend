<?php

namespace App\DataTables;

use App\Facades\UtilityFacades;
use App\Models\RequestDomain;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class RequestDomainDataTable extends DataTable
{
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
        ->addIndexColumn()
        ->editColumn('created_at', function ($request) {
            return UtilityFacades::date_time_format($request->created_at);
        })
        ->addColumn('action', function (RequestDomain $requestdomain) {
            return view('requestdomain.action', compact('requestdomain'));
        })
        ->editColumn('status', function (RequestDomain $requestdomain) {
            if($requestdomain->is_approved==1){
                return '<span class="badge rounded-pill bg-success p-2 px-3">'.__('Active').'</span>';
            }elseif($requestdomain->is_approved==2){
                return '<span class="badge rounded-pill bg-danger p-2 px-3">'.__('Inactive').'</span>';
            }else{
                return '<span class="badge rounded-pill bg-warning p-2 px-3">'.__('Pending').'</span>';
            }
        })
        ->editColumn('payment_status', function (RequestDomain $requestdomain) {
            if($requestdomain->payStatus->status == 0 && $requestdomain->payStatus->plan_id  == 1){
                return '<span class="badge rounded-pill bg-primary p-2 px-3">'.__('Free').'</span>';
            }elseif($requestdomain->payStatus->status ==2 &&  $requestdomain->payStatus->plan_id  > 1){
                return '<span class="badge rounded-pill bg-danger p-2 px-3">'.__('Cancel').'</span>';
            }elseif($requestdomain->payStatus->status == 1 &&  $requestdomain->payStatus->plan_id  > 1){
                return '<span class="badge rounded-pill bg-success p-2 px-3">'.__('Success').'</span>';
            }else{
                return '<span class="badge rounded-pill bg-warning p-2 px-3">'.__('Pending').'</span>';
            }
        })
        ->rawColumns(['action','status','payment_status']);
    }

    public function query(RequestDomain $model)
    {
        return $model->newQuery()->select(['request_domains.*','plans.name as plan_name'])
            ->join('orders','orders.domainrequest_id','=','request_domains.id')
            ->join('plans','orders.plan_id','=','plans.id');
    }

    public function html()
    {
        return $this->builder()
        ->setTableId('users-table')
        ->columns($this->getColumns())
        ->minifiedAjax()
        ->orderBy(1)
        ->language([
            "paginate" => [
                "next" => '<i class="fas fa-angle-right"></i>',
                "previous" => '<i class="fas fa-angle-left"></i>'
            ]
        ])
        ->parameters([
            "dom" =>  "
                           <'row'<'col-sm-12 p-0'><'col-sm-9 'B><'col-sm-3'f>>
                           <'row'<'col-sm-12'tr>>
                           <'row mt-3'<'col-sm-5'i><'col-sm-7'p>>
                           ",
            'buttons'   => [
            //     ['extend' => 'create', 'className' => 'btn btn-primary btn-sm no-corner add_module', 'action' => " function ( e, dt, node, config ) {
            //         window.location = '" . route('users.create') . "';

            //    }"],
                ['extend' => 'export', 'className' => 'btn btn-primary btn-sm no-corner',],
                ['extend' => 'print', 'className' => 'btn btn-primary btn-sm no-corner',],
                ['extend' => 'reset', 'className' => 'btn btn-primary btn-sm no-corner',],
                ['extend' => 'reload', 'className' => 'btn btn-primary btn-sm no-corner',],
                ['extend' => 'pageLength', 'className' => 'btn btn-primary btn-sm no-corner',],
            ],
            "scrollX" => true
        ])->language([
            'buttons' => [
                'create' => __('Create'),
                'export' => __('Export'),
                'print' => __('Print'),
                'reset' => __('Reset'),
                'reload' => __('Reload'),
                'excel' => __('Excel'),
                'csv' => __('CSV'),
                'pageLength' => __('Show %d rows'),
            ]
        ]);
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns()
    {

        return [
            Column::make('No')->title(__('No'))->data('DT_RowIndex')->name('DT_RowIndex')->searchable(false)->orderable(false),
            Column::make('name')->title(__('Name')),
            Column::make('email')->title(__('Email')),
            Column::make('domain_name')->title(__('Domain Name')),
            Column::make('plan_name')->name('plans.name')->title(__('Plan Name')),
            Column::make('status')->title(__('Status'))->orderable(false)->searchable(false),
            Column::make('payment_status')->title(__('Payment Status'))->orderable(false)->searchable(false),
            Column::make('created_at')->title(__('Created At')),
            Column::computed('action')->title(__('Action'))
                  ->exportable(false)
                  ->printable(false)
                  ->width(60)
                  ->addClass('text-center')
                  ->width('20%'),
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename()
    {
        return 'RequestDomain_' . date('YmdHis');
    }
}
