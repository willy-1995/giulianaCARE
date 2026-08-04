import { Link } from "react-router-dom";
import { useNavigate, useLocation } from "react-router-dom";
import { useRef, useState } from "react";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons";
import { faUser, faGear, faArrowLeft } from "@fortawesome/free-solid-svg-icons";
import "../styles/dashboard.scss";

function SubNavbar() {
  const [mobileMenue, setMobileMenue] = useState(false);
  const menueRef = useRef(null);

  const getAssetUrl = (path: string) => `${import.meta.env.BASE_URL}${path}`;
  const navigate = useNavigate();
  const location = useLocation();

  //Check location first
  const isSettingsPage = location.pathname === "/settings";

  //FUNCTIONS

  const toSettings = () => {
    navigate("/settings");
  };

  const toDashboard = () => {
    navigate("/dashboard"); // Passe den Pfad zu deinem Dashboard an
  };

  return (
    <div className="navbar">
      <div className="logo-div">
        {" "}
        <img
          src={getAssetUrl("media/giulianaCareCut.png")}
          alt="logo"
          id="logo"
        />
      </div>
      {isSettingsPage ? (
        <button className="desktop-button" onClick={toDashboard}>
          <FontAwesomeIcon icon={faArrowLeft} />
        </button>
      ) : (
        <button className="toSetting-button" onClick={toSettings}>
          <FontAwesomeIcon icon={faGear} />
        </button>
      )}
    </div>
  );
}

export default SubNavbar;
