<!DOCTYPE html>
<html>
<title>Missenger</title>
<link rel = "icon" href = "img/favicon.png"
      type = "image/x-icon">


<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">

<style>
    html,
    body {
        min-height: 100%;
        background: #ffffff;
        color: #1f2933;
        font-family: "Times New Roman", Georgia, Serif;
    }

    h1, h2, h3, h4, h5, h6 {
        font-family: "Playfair Display";
        letter-spacing: 5px;
    }
</style>
<body>
<!-- Navbar (sit on top) -->
<div class="w3-top">
    <div class="w3-bar w3-white w3-padding w3-card" style="letter-spacing:4px;">
        <!-- Right-sided navbar links. Hide them on small screens -->
        <div class="w3-right w3-hide-small">
            <a href="login" class="w3-bar-item w3-button">Login</a>
            <a href="register" class="w3-bar-item w3-button">Register</a>
        </div>
    </div>
</div>

<!-- Page content -->
<div class="w3-content" style="max-width:1100px">

    <!-- About Section -->
    <div class="w3-row w3-padding-64" id="about">
        <div class="w3-col m6 w3-padding-large w3-hide-small">
            <img src="img/linkedin_profile_image.png" class="w3-round w3-image w3-opacity-min" alt="Table Setting" width="600" height="750">
        </div>

        <div class="w3-col m6 w3-padding-large">
            <h1 class="w3-center">Messaging app!</h1>
            <p class="w3-large">Keeping in touch with friends and family has never been easier. If you have an internet connection, you can send a message to anyone, anywhere, free of charge.</p>
            <p class="w3-large">The chat application follows a typical client-server model. The clients, when log in to the server, identify themselves, and obtain the list of other clients that are connected to the server. Clients can either send a unicast message to any one of the other clients or a broadcast a message to all the other clients. Note that the clients maintain an active connection only with the server and not with any other clients. Consequently, all messages exchanged between the clients must flow through the server. Clients never exchange messages directly with each other. The server exists to facilitate the exchange of messages between the clients. The server can exchange control messages with the clients.</p>
        </div>
    </div>
    <hr>
</div>

<!-- Footer -->
<footer class="w3-center w3-light-grey w3-padding-32">
    <p>Missenger<a href="#home" title="W3.CSS" target="_blank" class="w3-hover-text-green"> chating app</a></p>
</footer>

</body>
</html>
