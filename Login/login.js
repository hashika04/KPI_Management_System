function login(){

    const username = document.getElementById("username").value;
    const password = document.getElementById("password").value;

    if(username === "supervisor" && password === "1234"){
        window.location.href = "../Dashboard/dashboard.php";
    }else{
        document.getElementById("error").innerText = "Invalid login";
    }

}