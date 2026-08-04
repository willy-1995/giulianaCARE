import React from "react";
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { Link } from "react-router-dom";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { faCircleCheck } from "@fortawesome/free-solid-svg-icons";
import Navbar from "./components/navbar";
import Footer from "./components/footer";
import "./styles/login.scss";

function Login() {
  //STATES
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [message, setMessage] = useState<string | React.JSX.Element>("");
  const [isloading, setIsLoading] = useState(false);
  const [attempts, setAttempts] = useState(0);
  const navigate = useNavigate();

  const loginHandler = async (e: any) => {
    e.preventDefault();
    console.log("Klick"); //LOG
    if (isloading) return;
    setIsLoading(true);

    try {
      const response = await fetch(
        "http://localhost/giulianaCare/api/auth/auth.php",
        {
          method: "POST",
          body: JSON.stringify({ email, password }),
          headers: { "Content-Type": "application/json" },
        },
      );

      const rawData = await response.text();
      console.log("Serverantwort: ", rawData);
      const profileData = JSON.parse(rawData);
      console.log("Anfrage gesendet");
      if (profileData.success) {
        setMessage(
          <span id="login-check-message">
            <FontAwesomeIcon icon={faCircleCheck} className="icon" /> Login
            erfolgreich!
          </span>,
        );

        //TOKEN
        localStorage.setItem("token", profileData.token);
        console.log("Token gespeichert:", localStorage.getItem("token"));

        //REDIRECT
        setTimeout(() => {
          navigate("/dashboard");
        }, 3000);
      } else {
        //count attempts
        const newAttempts = attempts + 1;
        setAttempts(newAttempts);

        if (profileData.remaining_attempts === 0) {
          setMessage("Zu viele Fehlversuche. Bitte warte einen Moment.");
        } else {
          setMessage(
            `Login falsch. Noch ${profileData.remaining_attempts ?? "einige"} Versuche.`,
          );
        }

        // Rate-Limit: Bei jedem Fehler 2 Sekunden Sperre,
        // ab 3 Fehlern 30 Sekunden "Abkühlzeit"
        const cooldown = newAttempts >= 3 ? 30000 : 2000;
        setTimeout(() => setIsLoading(false), cooldown);
      }
    } catch (error) {
      console.log(error);
      setMessage("Serverfehler. Bitte versuche es später erneut!");
      setIsLoading(false);
    }
  };

  return (
    <div className="body-div login-div">
      <Navbar />
      <div className="distance-div">
        <form id="login-form" onSubmit={loginHandler}>
          <h1>Login</h1>
          <input
            type="email"
            name="email"
            placeholder="E-Mail-Adresse"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
          <input
            type="password"
            name="password"
            placeholder="Passwort"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />
          <button type="submit" disabled={isloading}>
            {isloading
              ? attempts >= 3
                ? "Gesperrt (30s)"
                : "Prüfe..."
              : "Einloggen"}
          </button>
          <Link to={"/telecare"} id="to-landing">
            <span className="link-normal">Zurück</span>
          </Link>
        </form>
        <div className="login-msg-div">{message}</div>
      </div>

      <Footer />
    </div>
  );
}

export default Login;
