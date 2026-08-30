import { useNavigate } from "react-router-dom";
import { Link } from "react-router-dom";
import { useState } from "react";
import { useEffect } from "react";
import SubNavbar from "./components/navbar_sub";
import Footer from "./components/footer";
import { deleteUser } from "../assets/deleter";
import "./styles/settings.scss";
import { faL } from "@fortawesome/free-solid-svg-icons";

export default function Settings() {
  //LOGOUT
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);

  //==========================
  //Functions
  //==========================

  //Logout
  const handleLogout = () => {
    localStorage.removeItem("token");

    navigate("/login");
  };

  //Delete Account
  const handleDeleteAccount = async () => {
    if (confirm("Abonnement kündigen und Account wirklich löschen?")) {
      const navigate = useNavigate();

      const result = await deleteUser(setLoading);

      if (result.success) {
        navigate("/registration", {
          state: {
            message:
              "Dein Account wurde gelöscht und das Abonnement zum nächstmöglichen Zeitpunkt gekündigt.",
          },
        });
      } else {
        // Fehlerbehandlung (z. B. Banner oder Toast anzeigen)
        alert(result.message || "Fehler beim Löschen des Accounts");
      }
    }
  };

  return (
    <div className="body-div settings-div">
      <SubNavbar />
      <div className="nav-div">
        <Link to="/dashboard" className="link">
          <h3>Dashboard</h3>
        </Link>
      </div>
      <div className="setting-sections-div">
        <div className="setting-section">
          <h2>Dein Account</h2>
        </div>
        <div className="setting-section">
          <h3>Abonnement</h3>
        </div>
        <div className="settings-section">
          <button onClick={handleLogout} className="logout">
            Ausloggen
          </button>
          <button
            onClick={handleDeleteAccount}
            className="deleteAccount"
          ></button>
        </div>
      </div>
      <Footer />
    </div>
  );
}
