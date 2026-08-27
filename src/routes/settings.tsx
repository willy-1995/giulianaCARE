import { useNavigate } from "react-router-dom";
import { Link } from "react-router-dom";
import { useState } from "react";
import { useEffect } from "react";
import SubNavbar from "./components/navbar_sub";
import Footer from "./components/footer";
import "./styles/settings.scss";

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
        </div>
        <div className="setting-section">
          <h2>Abonnement</h2>
        </div>
        <div className="settings-section">
          <button onClick={handleLogout} className="logout">
            Ausloggen
          </button>
        </div>
      </div>
      <Footer />
    </div>
  );
}
