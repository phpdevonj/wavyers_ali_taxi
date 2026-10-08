
<?php
    $auth_user = authSession();
?>
{{ Form::open(['route' => ['deactivated-driver.destroy', $id], 'method' => 'delete','data--submit'=>'deactivateddriver'.$id]) }}
<div class="d-flex justify-content-end align-items-center">
    @if($auth_user->can('deactivated driver delete'))
    <a class="mr-2 text-danger" href="javascript:void(0)" data--submit="deactivateddriver{{$id}}"
        data--confirmation='true' data-title="{{ __('message.delete_permanently') }}"
        title="{{ __('message.delete_permanently') }}"
        data-message='{{ __("message.delete_permanently_confirm") }}'>
        <i class="fas fa-trash-alt"></i>
    </a>
    @endif
</div>
{{ Form::close() }}
