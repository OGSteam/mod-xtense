const auth = ['system', 'ranking', 'empire', 'messages'];


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

function setAllCheckboxStatus(isChecked) {
  groups_id.forEach(groupId => {
    auth.forEach(authValue => {
      const checkboxId = `${authValue}_${groupId}`;
      document.getElementById(checkboxId).checked = isChecked;
    });
  });
}



function getXtensePluginUrl() {
  const XTENSE_URL_PATTERN = /(.*)index\.php\?action=xtense/gm;
  const currentUrl = window.location.href;
  const match = XTENSE_URL_PATTERN.exec(currentUrl);
  return match ? match[1] : "";
}
