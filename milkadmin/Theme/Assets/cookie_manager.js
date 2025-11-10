/**
 * Cookie Management
 */

class CookieManager {
    // Sets a cookie
    static set(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/";
    }

    // Gets the value of a cookie
    static get(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    // Deletes a single cookie while preserving others
    static del(name) {
        CookieManager.set(name, "", -1);
    }

    /*
    function checkCookie()
    {
    document.cookie = 'check_cookie';
    var testcookie = (document.cookie.indexOf('check_cookie') != -1) ? true : false;
    return testcookie;
    }

    */
}