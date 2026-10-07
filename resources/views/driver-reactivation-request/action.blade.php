
<?php
    $auth_user = authSession();
?>
<div class="d-flex justify-content-end align-items-center">
    @if($status === 'pending' && $auth_user->can('driver reactivation request action'))
        {{ Form::open(['route' => ['driver-reactivation-request.resolve', $id], 'method' => 'post','data--submit'=>'reactivate'.$id]) }}
        {{ Form::hidden('action', 'reactivate') }}
        <a class="mr-2 text-primary" href="javascript:void(0)" data--submit="reactivate{{$id}}"
            data--confirmation='true' data-title="{{ __('message.reactivate') }}"
            title="{{ __('message.reactivate') }}"
            data-message='{{ __("message.reactivate_driver_confirm") }}'>
            <i class="fas fa-undo"></i>
        </a>
        {{ Form::close() }}

        {{ Form::open(['route' => ['driver-reactivation-request.resolve', $id], 'method' => 'post','data--submit'=>'leavedeactivated'.$id]) }}
        {{ Form::hidden('action', 'leave_deactivated') }}
        <a class="mr-2 text-secondary" href="javascript:void(0)" data--submit="leavedeactivated{{$id}}"
            data--confirmation='true' data-title="{{ __('message.leave_deactivated') }}"
            title="{{ __('message.leave_deactivated') }}"
            data-message='{{ __("message.leave_deactivated_confirm") }}'>
            <i class="fas fa-pause-circle"></i>
        </a>
        {{ Form::close() }}

        {{ Form::open(['route' => ['driver-reactivation-request.resolve', $id], 'method' => 'post','data--submit'=>'permdelete'.$id]) }}
        {{ Form::hidden('action', 'delete_permanently') }}
        <a class="mr-2 text-danger" href="javascript:void(0)" data--submit="permdelete{{$id}}"
            data--confirmation='true' data-title="{{ __('message.delete_permanently') }}"
            title="{{ __('message.delete_permanently') }}"
            data-message='{{ __("message.delete_permanently_confirm") }}'>
            <i class="fas fa-trash-alt"></i>
        </a>
        {{ Form::close() }}
    @else
        <span class="text-muted text-capitalize">{{ str_replace('_', ' ', $status) }}</span>
    @endif
</div>
