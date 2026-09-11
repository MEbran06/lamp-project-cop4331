const baseUrl = "http://localhost:80"
let csrf_token = "";

function saveCookie(isLogged, csrf_token, minutes) {
  let date = new Date();
  date.setTime(date.getTime() + minutes * 60 * 1000);
  document.cookie =
    "isLogged=" +
    isLogged +
    ",csrf_token="+
    csrf_token +
    ";expires=" +
    date.toGMTString() +
    ";path=/";
}

function readCookie()
{
    isLogged = 0;
    let data = document.cookie;
    let splits = data.split(";");
    for (var i = 0; i < splits.length; i++) {
        let pair = splits[i].trim();
        let tokens = pair.split(",");
        for (var j = 0; j < tokens.length; j++) {
            let keyVal = tokens[j].trim().split("=");
            if (keyVal[0] === "isLogged") {
                isLogged = parseInt(keyVal[1].trim());
            }
            if (keyVal[0] === "csrf_token") {
                csrf_token = keyVal[1].trim();
            }
        }
    }

    if (isLogged == 0 || isNaN(isLogged)) {
        window.location.href = "index.html";
    }  
}

function doLogout() {
  isLogged = 0;
  csrf_token="";
  document.cookie = "csrf_token=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
  document.cookie = "isLogged=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
  window.location.href = "index.html";
}