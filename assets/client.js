$(document).ready(function () {

    $('.btn-rebuild').on('click', function () {
        $('#modalCustomCloudzone').modal('show');
        $('#modalCustomCloudzone .modal-title').text('Cài đặt lại VPS');
        var ip = $(this).attr('data-ip');
        var id = $(this).attr('data-id');

        var html = '';
        html += '<div class="text-center text-bold mauden mb-2">';
        html += 'Bạn có muốn cài đặt lại VPS <strong class="text-danger">'+ ip +'</strong> này không?';
        html += '</div>';
        html += '<div class="text-danger text-left mt-2 mb-2">';
        html += 'Lưu ý: Hành động này rất nguy hiểm, nó có thể xóa VPS và cài đặt lại. Quý khách vui lòng kiểm tra lại các VPS cần cài đặt lại.';
        html += '</div>';
        $('#noticationModalAll').html(html);
        $('#buttonSubmitCloudzone').fadeIn();
        $('#buttonSubmitCloudzone').attr('data-action', 'rebuild');
        $('#buttonSubmitCloudzone').attr('data-id', id);
        $('#buttonSubmitCloudzone').attr('data-ip', ip);
        $('#buttonFinishCloudzone').fadeOut();
    });


    $('#buttonSubmitCloudzone').on('click', function () {
        var action = $('#buttonSubmitCloudzone').attr('data-action');
        var id = $('#buttonSubmitCloudzone').attr('data-id');
        switch (action) {
            case 'rebuild':
                checkOsWhenRebuild(id);
                break;
            case 'check-os-rebuild':
                comfirmOsWhenRebuild();
                break;
            case 'comfirm-rebuild':
                confirmRebuild(id);
                break;
        
            default:
                $('#noticationModalAll').html('<span class="text-danger">Hành động không tồn tại</span>');
                $('#buttonSubmitCloudzone').fadeOut();
                $('#buttonFinishCloudzone').fadeIn();
                break;
        }
    });

    function checkOsWhenRebuild(id) {
        $.ajax({
            url: "clientarea.php?action=productdetails",
            data: {token: csrfToken, id: id, module_addon: 'cloudnest', modop: 'custom', a: 'check-os'},
            type: 'post',
            // dataType: "json",
            beforeSend: function () {
                var html = '<div class="text-center"><div class="vong-xoay"><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div></div>';
                $('#noticationModalAll').html(html);
                $('#buttonSubmitCloudzone').attr('disabled', true);
            },
            success: function (data) {
                if (data.error) {
                    $('#noticationModalAll').html('<div" class="text-center text-danger">'+ data.message +'</div>');
                    $('#buttonSubmitCloudzone').fadeOut();
                    $('#buttonFinishCloudzone').fadeIn();
                }
                else {
                    $('#buttonSubmitCloudzone').attr('data-action', 'check-os-rebuild');
                    $('#buttonSubmitCloudzone').attr('data-id', id);

                    var html = '';
                    html += '<div class="form-group mt-4 text-left">';
                    html += '<label for="os_select">Chọn hệ điều hành</label>';
                    html += '<select class="form-control" id="os_select">';
                    $.each(data['list-os'], function (index, value) {
                        html += '<option value="' + value['os-id'] + '">' + value['os-name'] + '</option>';
                    });
                    html += '</select>';
                    html += '</div>';
                    $('#noticationModalAll').html(html);
                }
                $('#buttonSubmitCloudzone').attr('disabled', false);
            },
            error: function (e) {
                console.log('lỗi');
                console.log(e);
                $('#noticationModalAll').html('<div" class="text-center text-danger">Truy vấn VPS lỗi.</div>');
                $('#buttonSubmitCloudzone').attr('disabled', false);
                $('#buttonSubmitCloudzone').fadeOut();
                $('#buttonFinishCloudzone').fadeIn();
            }
        });
    }

    function comfirmOsWhenRebuild()
    {
        var os = $('#os_select option:selected').text();
        var os_id = $('#os_select option:selected').val();
        var ip = $('#buttonSubmitCloudzone').attr('data-ip');
        var html = '';
        html += '<div class="text-left text-bold mauden mb-2">';
        html += 'Bạn sẽ cài đặt lại VPS '+ ip +' với thông tin như sau: <br>';
        html += 'Hệ điều hành: ' + os;
        html += '<input type="hidden" name="os" id="os" value="' + os_id + '">';
        html += '<input type="hidden" name="os-name" id="os-name" value="' + os + '">';
        html += '</div>';
        html += '<div class="text-danger text-left mt-2 mb-2">';
        html += 'Lưu ý: Hành động này rất nguy hiểm, nó có thể xóa VPS và cài đặt lại. Quý khách vui lòng kiểm tra lại các VPS cần cài đặt lại.';
        html += '</div>';
        $('#noticationModalAll').html(html);
        $('#buttonSubmitCloudzone').attr('data-action', 'comfirm-rebuild');
    }

    function confirmRebuild(id) {
        var os = $('#os').val();
        var osName = $('#os-name').val();
        var oldOs = $('#old-os').val();
        $.ajax({
            url: "clientarea.php?action=productdetails",
            data: {token: csrfToken, id: id, module_addon: 'cloudnest', modop: 'custom', a: 'comfirm-rebuild', os: os, osName: osName, oldOs: oldOs},
            type: 'post',
            // dataType: "json",
            beforeSend: function () {
                var html = '<div class="text-center"><div class="vong-xoay"><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div></div>';
                $('#noticationModalAll').html(html);
                $('#buttonSubmitCloudzone').attr('disabled', true);
            },
            success: function (data) {
                // console.log('====================================');
                // console.log(data);
                // console.log('====================================');
                if (data.error) {
                    $('#noticationModalAll').html('<div" class="text-center text-danger">'+ data.message +'</div>');
                    $('#buttonSubmitCloudzone').fadeOut();
                    $('#buttonFinishCloudzone').fadeIn();
                    $('#buttonSubmitCloudzone').attr('disabled', false);
                }
                else {
                    $('#noticationModalAll').html('<div" class="text-center text-success">'+ data.message +'</div>');
                    $('#buttonSubmitCloudzone').fadeOut();
                    $('#buttonFinishCloudzone').fadeIn();
                    $('#buttonSubmitCloudzone').attr('disabled', false);
                    
                    $('.form-action-vps').fadeOut();
                }
            },
            error: function (e) {
                console.log('lỗi');
                console.log(e);
                $('#noticationModalAll').html('<div" class="text-center text-danger">Truy vấn VPS lỗi.</div>');
                $('#buttonSubmitCloudzone').attr('disabled', false);
                $('#buttonSubmitCloudzone').fadeOut();
                $('#buttonFinishCloudzone').fadeIn();
                setTimeout(() => {
                    location.reload();
                }, 3000);
            }
        });
    }

});
