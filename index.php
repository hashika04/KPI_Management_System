<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<style>
body{
    /*background:
        radial-gradient(circle at top left, rgba(139, 92, 246, 0.08), transparent 30%),
        radial-gradient(circle at bottom right, rgba(163, 230, 53, 0.08), transparent 30%),
        #161616;*/
    background: linear-gradient(135deg, #8b5cf6, #a3e635);
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    font-family: Arial;
}

.login-box{
    background: #1c1c1f;
    box-shadow: 80px 80px 80px 90px rgba(0,0,0,0.35);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 28px;
    padding:40px;
    border-radius:15px;
    width:300px;
    text-align:center;
    color:white;
}

input{
    width:100%;
    padding:10px;
    margin:10px 0;
    border:none;
    border-radius:8px;
}

button{
    width:100%;
    padding:10px;
    border:none;
    border-radius:20px;
    background:white;
    cursor:pointer;
}
</style>
</head>

<body>

<div class="login-box">
<h2>KPI Management System</h2>

<input type="text" id="username" placeholder="Username">
<input type="password" id="password" placeholder="Password">

<button onclick="login()">LOG IN</button>

<p id="error" style="color:red;"></p>

</div>

<script>

function login(){

    const username = document.getElementById("username").value;
    const password = document.getElementById("password").value;

    if(username === "supervisor" && password === "1234"){
        window.location.href = "Dashboard/dashboard.php";
    }else{
        document.getElementById("error").innerText = "Invalid login";
    }

}

</script>

</body>
</html>