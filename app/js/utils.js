function saveCookie(userId, minutes) {
  let date = new Date();
  date.setTime(date.getTime() + minutes * 60 * 1000);
  document.cookie =
    "userId=" +
    userId +
    ";expires=" +
    date.toGMTString() +
    ";path=/";
}

function readCookie()
{
    userId = -1;
    let data = document.cookie;
    let splits = data.split(";");
    for (var i = 0; i < splits.length; i++) {
        let pair = splits[i].trim();
        let tokens = pair.split(",");
        for (var j = 0; j < tokens.length; j++) {
            let keyVal = tokens[j].trim().split("=");
            if (keyVal[0] === "userId") {
                userId = parseInt(keyVal[1].trim());
            }
        }
    }

    if (userId < 0 || isNaN(userId)) {
    window.location.href = "index.html";
    } else {
        let userNameEl = document.getElementById("userName");
        if (userNameEl) {
        userNameEl.innerHTML = '<span>user logged in</span>';
        }
    }
}

function doLogout() {
  userId = 0;
  document.cookie = "userId=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
  window.location.href = "index.html";
}