var auth = ['system', 'ranking', 'empire', 'messages'];


function check_col(type, El) {
    let status = El.checked;
    for (let i in groups_id) {
        document.getElementById(type + '_' + groups_id[i]).checked = status;
    }
    El.checked = status;
}

function check_row(id, El) {
    let status = El.checked;
    for (let i = 0; i < auth.length; i++) {
        document.getElementById(auth[i] + '_' + id).checked = status;
    }
    El.checked = status;
}

function set_all(status) {
    for (let i in groups_id) {
        for (var a = 0; a < auth.length; a++) {
            document.getElementById(auth[a] + '_' + groups_id[i]).checked = status;
        }
    }
}

function get_xtense_url() {

    const regex = /(.*)index\.php\?action=xtense/gm;
    const str = window.location.href;
    let m;

    if ((m = regex.exec(str)) !== null) {
        // This is necessary to avoid infinite loops with zero-width matches
        return m[1];
    } else {
        return "";
    }
}

function winOpen(El) {
    try {
        window.opener.open(El.href);
        return false;
    } catch (e) {
    }
}