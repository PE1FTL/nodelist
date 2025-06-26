jQuery(document).ready(function ($) {
    const modal = new bootstrap.Modal(document.getElementById('nodelist-modal'));
    const form = $('#nodelist-form');
    const notice = $('#nodelist-modal-notice');
    const captchaWrapper = $('#nodelist-captcha-wrapper');

    function resetForm() {
        form[0].reset();
        $('#nodelist-id').val('');
        form.find('input, textarea, select').prop('readonly', false);
        notice.hide().removeClass('alert-success alert-danger');
        captchaWrapper.hide();
    }

    function setFormState(isReadOnly) {
        form.find('input, textarea').prop('readonly', isReadOnly);
        form.find('input[type="checkbox"]').on('click', function(e) {
            if (isReadOnly) {
                e.preventDefault();
            }
        });
    }

    $('.nodelist-row').on('click', function () {
        const entryId = $(this).data('id');
        resetForm();
        $.ajax({
            url: nodelist_ajax_object.ajax_url,
            type: 'POST',
            data: { action: 'nodelist_get_details', nonce: nodelist_ajax_object.nonce, id: entryId },
            success: function (response) {
                if (response.success) {
                    const data = response.data;
                    $('#nodelist-id').val(data.id);
                    $('#nodelist-nodecall').val(data.nodecall);
                    $('#nodelist-qth').val(data.qth);
                    $('#nodelist-locator').val(data.locator);
                    $('#nodelist-sysop').val(data.sysop);
                    $('#nodelist-name').val(data.name);
                    $('#nodelist-sysopemail').val(data.sysopemail);
                    $('#nodelist-hf').prop('checked', data.hf == 1);
                    $('#nodelist-hfportnr').val(data.hfportnr);
                    $('#nodelist-telnet').prop('checked', data.telnet == 1);
                    $('#nodelist-telneturl').val(data.telneturl);
                    $('#nodelist-telnetport').val(data.telnetport);
                    $('#nodelist-ax25udp').prop('checked', data.ax25udp == 1);
                    $('#nodelist-ax25udpurl').val(data.ax25udpurl);
                    $('#nodelist-ax25udpport').val(data.ax25udpport);
                    $('#nodelist-bemerkung').val(data.bemerkung);
                    $('#nodelistModalLabel').text('Details für ' + data.nodecall);
                    captchaWrapper.hide();
                    if (nodelist_ajax_object.is_admin) {
                        $('#nodelist-submit').text('Änderungen speichern').show();
                        setFormState(false);
                    } else {
                        $('#nodelist-submit').hide();
                        setFormState(true);
                    }
                    modal.show();
                } else {
                    alert('Fehler: ' + response.data);
                }
            }
        });
    });

    $('#nodelist-new-entry').on('click', function () {
        resetForm();
        setFormState(false);
        $('#nodelistModalLabel').text('Neuen Eintrag erstellen');
        $('#nodelist-submit').text('Zur Genehmigung einreichen').show();
        captchaWrapper.show();
        modal.show();
    });

    $('#nodelist-submit').on('click', function () {
        const entryId = $('#nodelist-id').val();
        let action = entryId ? 'nodelist_update_entry' : 'nodelist_create_entry';
        
        if (action === 'nodelist_create_entry' && (!$('#nodelist-nodecall').val() || !$('#nodelist-sysopemail').val())) {
             notice.text('Nodecall und SysOp E-Mail sind Pflichtfelder.').removeClass('alert-success').addClass('alert-danger').show();
             return;
        }

        const formData = form.serializeArray().reduce(function(obj, item) {
            obj[item.name] = item.value;
            return obj;
        }, {});
        
        formData.hf = $('#nodelist-hf').is(':checked');
        formData.telnet = $('#nodelist-telnet').is(':checked');
        formData.ax25udp = $('#nodelist-ax25udp').is(':checked');
        formData.action = action;
        formData.nonce = nodelist_ajax_object.nonce;

        $.ajax({
            url: nodelist_ajax_object.ajax_url,
            type: 'POST',
            data: formData,
            success: function (response) {
                if (response.success) {
                    notice.text(response.data).removeClass('alert-danger').addClass('alert-success').show();
                    setTimeout(function() {
                        modal.hide();
                        location.reload();
                    }, 2000);
                } else {
                    notice.text('Fehler: ' + response.data).removeClass('alert-success').addClass('alert-danger').show();
                }
            },
            error: function () {
                notice.text('Ein unbekannter Fehler ist aufgetreten.').removeClass('alert-success').addClass('alert-danger').show();
            }
        });
    });
});