import { useNavigate } from "react-router-dom";
import { Link } from "react-router-dom";
import { useState } from "react";
import { useEffect } from "react";
import SubNavbar from "./components/navbar_sub";
import Footer from "./components/footer";

export default function Settings() {
  //LOGOUT
  const navigate = useNavigate();
  const handleLogout = () => {
    localStorage.removeItem("token");

    navigate("/login");
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
          <h2>Account</h2>
          <ul>
            <li>
              <p>Zugangsdaten bearbeiten</p>
            </li>
            <li>
              <p>Zahlungsinformationen bearbeiten</p>
            </li>
            <li>
              <p>Account löschen</p>
            </li>
          </ul>
        </div>
        <div className="setting-section">
          <h2>Abonnement</h2>
          <ul>
            <li>
              <p>Abo pausieren</p>
            </li>
            <li>
              <p>Abo kündigen</p>
            </li>
          </ul>
        </div>
        <div className="settings-section">
          <h3 onClick={handleLogout} className="logout">
            Ausloggen
          </h3>
        </div>
      </div>
      <Footer />
    </div>
  );
}
