jQuery(function ($) {
    function loadTable() {
        $.post(uumc_ajax.ajax_url, {
            action: 'uumc_get_attachments',
            _ajax_nonce: uumc_ajax.nonce
        }, function (response) {
            if (response.success) {
                let rows = response.data.map(item => `
                    <tr>
                        <td><input type="checkbox" class="umc-select" value="${item.id}" /></td>
                        <td>${item.id}</td>
                        <td>${item.thumb}</td>
                        <td>${item.filename}</td>
                        <td><a href="${item.url}" target="_blank">${item.url}</a></td>
                    </tr>
                `);
                $('#umc-media-table tbody').html(rows.join(''));
                $('#umc-media-table').DataTable();
            }
        });
    }

    loadTable();

    $('#umc-select-all').on('click', function () {
        $('.umc-select').prop('checked', this.checked);
    });

    function deleteAttachments(ids) {
        if (!confirm('Are you sure you want to delete selected media?')) return;

        $.post(uumc_ajax.ajax_url, {
            action: 'uumc_delete_attachments',
            _ajax_nonce: uumc_ajax.nonce,
            ids: ids
        }, function (response) {
            if (response.success) {
                alert(response.data);
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    }

    $('#umc-delete-selected').on('click', function () {
        let ids = $('.umc-select:checked').map(function () {
            return $(this).val();
        }).get();
        if (ids.length > 0) deleteAttachments(ids);
        else alert('No media selected.');
    });

    $('#umc-delete-all').on('click', function () {
        let all = $('.umc-select').map(function () {
            return $(this).val();
        }).get();
        if (all.length > 0) deleteAttachments(all);
        else alert('No media to delete.');
    });
});
