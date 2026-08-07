importScripts('https://www.gstatic.com/firebasejs/7.14.3/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/7.14.3/firebase-messaging.js');
/*Update this config*/
var firebaseConfig = {
    apiKey: "AIzaSyDFbsRkwvTwZJoFHzmLmb0yUTOEijJ4Tw4",
    authDomain: "akbrnyapp.firebaseapp.com",
    databaseURL: "https://akbrnyapp.firebaseio.com",
    projectId: "akbrnyapp",
    storageBucket: "akbrnyapp.appspot.com",
    messagingSenderId: "744234927044",
    appId: "1:744234927044:web:ec0c2c58672c03ba75da91",
    measurementId: "G-CYLXNJ02WF"
};

firebase.initializeApp(firebaseConfig);

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function(payload) {
    console.log('[firebase-messaging-sw.js] Received background message ', payload);
    // Customize notification here
    const notificationTitle = payload.data.title;
    const notificationOptions = {
        body: payload.data.body,
        icon: 'http://localhost/gcm-push/img/icon.png',
        image: 'http://localhost/gcm-push/img/d.png'
    };

    return self.registration.showNotification(notificationTitle,
        notificationOptions);
});
