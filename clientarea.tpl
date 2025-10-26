<link href="modules/servers/cloudnest/assets/client.css?token={$time}" rel="stylesheet">
<script src="modules/servers/cloudnest/assets/client.js?token={$time}"></script>

{if !empty($notification)}
  <div class="bd-alert">
    {if $error}
      <div class="alert alert-danger">
          {$notification}
      </div>
    {else}
      <div class="alert alert-success">
          {$notification}
      </div>
    {/if}
  <div>
{/if}
<div class="cloudnest-button-action text-center mt-2 mb-2">
  {if $status_vps == 'on' || $status_vps == 'off'}
    {if $status_vps == 'off'}
      <span class="mr-2 form-action-vps">
        <form action="clientarea.php?action=productdetails" method="post">
          <input type="hidden" name="id" value="{$serviceid}" />
          <input type="hidden" name="modop" value="custom" />
          <input type="hidden" name="module_addon" value="cloudnest" />
          <input type="hidden" name="a" value="on" />
          <input type="submit" value="Bật" class="btn btn-success btn-sm" />
        </form>
      </span>
    {/if}
    {if $status_vps == 'on'}
      <span class="mr-2 form-action-vps">
        <form action="clientarea.php?action=productdetails" method="post">
          <input type="hidden" name="id" value="{$serviceid}" />
          <input type="hidden" name="module_addon" value="cloudnest" />
          <input type="hidden" name="modop" value="custom" />
          <input type="hidden" name="a" value="off" />
          <input type="submit" value="Tắt" class="btn btn-warning text-light btn-sm" />
        </form>
      </span>
    {/if}
    <span class="mr-2 form-action-vps">
      <form action="clientarea.php?action=productdetails" method="post" id="turnOffForm">
        <input type="hidden" name="id" value="{$serviceid}" />
        <input type="hidden" name="module_addon" value="cloudnest" />
        <input type="hidden" name="modop" value="custom" />
        <input type="hidden" name="a" value="restart" />
        <input type="submit" value="Khởi động lại" class="btn btn-info btn-sm" />
      </form>
    </span>
    <span class="mr-2 form-action-vps">
        <input type="hidden" id="old-os" value="{$old_os}" />
        <button 
          type="button" class="btn btn-default btn-sm btn-rebuild"
          data-id="{$serviceid}" data-ip="{$service_ip}"
        >
          Cài đặt lại
        </button>
    </span>
  {/if}
  {if $status_vps != 'cancel' || $status_vps != 'change_user'  || $status_vps != 'delete_vps'}
    <span class="mr-2">
      <form action="clientarea.php?action=productdetails" method="post">
        <input type="hidden" name="id" value="{$serviceid}" />
        <input type="hidden" name="module_addon" value="cloudnest" />
        <input type="hidden" name="modop" value="custom" />
        <input type="hidden" name="a" value="cancel" />
        <input type="submit" value="Huỷ VPS" class="btn btn-danger btn-sm" />
      </form>
    </span>
  {/if}
</div>
{if $status_vps != 'cancel' || $status_vps != 'change_user'  || $status_vps != 'delete_vps'}
  <div class="cloudzone-area-info mt-2 mb-2">
    <div class="row">
      <div class="col-md-11 font-weight-bold">
        <h4>Thông tin cấu hình</h4>
      </div>
      <div class="col-md-5 text-right font-weight-bold">
        IP:
      </div>
      <div class="col-md-7 text-left">
        {$service_ip}
      </div>
      <div class="col-md-5 text-right font-weight-bold">
        Username:
      </div>
      <div class="col-md-7 text-left">
        {$service_username}
      </div>
      <div class="col-md-5 text-right font-weight-bold">
        Mật khẩu:
      </div>
      <div class="col-md-7 text-left">
        {$service_password}
      </div>
      <div class="col-md-5 text-right font-weight-bold">
        Hệ điều hành:
      </div>
      <div class="col-md-7 text-left">
        {$old_os}
      </div>
    </div>
  </div>
{/if}

<div class="modal fade" id="modalCustomCloudzone">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title"></h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="noticationModalAll" class="text-center">

        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-default" data-dismiss="modal">Hủy</button>
        <button type="button" class="btn btn-primary" id="buttonSubmitCloudzone">Xác nhận</button>
        <button type="button" class="btn btn-danger" id="buttonFinishCloudzone" data-dismiss="modal">Hoàn thành</button>
      </div>
    </div>
  </div>
</div>