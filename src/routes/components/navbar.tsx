import { Link } from "react-router-dom";
import { useRef, useState } from "react";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons";
import { faUser } from "@fortawesome/free-solid-svg-icons";

function Navbar() {
  const [mobileMenue, setMobileMenue] = useState(false);
  const menueRef = useRef(null);

  const getAssetUrl = (path: string) => `${import.meta.env.BASE_URL}${path}`;

  //FUNCTIONS
  const showMobileMenue = () => {
    setMobileMenue((prev) => !prev);
  };
  return (
    <div className="navbar">
      <Link to={"/telecare"} className="logo-div">
        {" "}
        <img
          src={getAssetUrl("media/giulianaCareCut.png")}
          alt="logo"
          id="logo"
        />
      </Link>

      <div className="user-access-div desktop-access">
        <Link to={"/login"} className="link">
          Login
        </Link>
        <Link to={"/registration"} className="link registration-btn">
          Registrieren
        </Link>
      </div>
      <div className="user-access-div mobile-access">
        <FontAwesomeIcon
          icon={faUser}
          className="ui-icon"
          onClick={showMobileMenue}
        />
        {mobileMenue && (
          <div className="mobile-menue" ref={menueRef}>
            <Link to={"/login"} className="link">
              Login
            </Link>
            <Link to={"/registration"} className="link registration-btn">
              Registrieren
            </Link>
          </div>
        )}
      </div>
    </div>
  );
}

export default Navbar;
