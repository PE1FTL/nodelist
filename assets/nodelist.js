jQuery(document).ready(function($) {
    // Zeilenklick für Details
    $('.nodelist-row').click(function() {
        var id = $(this).data('id');
        $.ajax({
            url: nodelist_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'nodelist_get',
                id: id,
                nonce: nodelist_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    $('#nodelist-form').find('input, textarea').each(function() {
                        var name = $(this).attr('name');
                        if (name && name !== 'captcha_answer' && name !== 'captcha_correct') {
                            if ($(this).is(':checkbox')) {
                                $(this).prop('checked', data[name] == '1');
                            } else {
                                $(this).val(data[name]);
                            }
                            $(this).prop('disabled', !nodelist_vars.is_admin);
                        }
                    });
                    $('#nodelistModal').modal('show');
                }
            }
        });
    });

    // Neuer Eintrag
    $('#submit-node').click(function() {
        $.ajax({
            url: nodelist_vars.ajax_url,
            type: 'POST',
            data: $('#nodelist-form').serialize(),
            success: function(response) {
                if (response.success) {
                    alert('Eintrag eingereicht!');
                    $('#nodelistModal').modal('hide');
                    location.reload();
                } else {
                    alert(response.data);
                }
            }
        });
    });

    // Admin Speichern
    $('#save-node').click(function() {
        $.ajax({
            url: nodelist_vars.ajax_url,
            type: 'POST',
            data: $('#nodelist-form').serialize(),
            success: function(response) {
                if (response.success) {
                    alert('Gespeichert!');
                    $('#nodelistModal').modal('hide');
                    location.reload();
                } else {
                    alert(response.data);
                }
            }
        });
    });

    // Admin Freigabe/Löschen
    $('.approve-entry').click(function() {
        var id = $(this).data('id');
        $.ajax({
            url: nodelist_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'nodelist_save',
                pending_id: id,
                nonce: nodelist_vars.nonce
            },
            success: function() {
                location.reload();
            }
        });
    });

    $('.delete-entry').click(function() {
        if (confirm('Eintrag löschen?')) {
            var id = $(this).data('id');
            $.ajax({
                url: nodelist_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'nodelist_delete',
                    id: id,
                    nonce: nodelist_vars.nonce
                },
                success: function() {
                    location.reload();
                }
            });
        }
    });
});