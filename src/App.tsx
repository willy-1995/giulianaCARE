import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { BrowserRouter as Router, Routes, Route, Link } from "react-router-dom";
import Landing from "./routes/landing";
import TeleCare from "./routes/telecare";
import Registration from "./routes/registration";
import Login from "./routes/login";
import { EditUserAccount } from "./routes/editUserAccount";
import UserDataForm from "./assets/userdataform";
import { ProtectedRoute } from "./routes/protectedRoutes";
import Dashboard from "./routes/dashboard";
import Settings from "./routes/settings";
import Request from "./routes/request";
import "./routes/styles/main.scss";

function App() {
  // Hier definieren wir die Zustände für Token und ID
  const [token, setToken] = useState<string | null>(
    localStorage.getItem("token"),
  );
  const [myId, setMyId] = useState<number | null>(
    localStorage.getItem("myId") ? Number(localStorage.getItem("myId")) : null,
  );
  return (
    <div>
      <Router>
        {/*for protected sites! */}
        <Routes>
          <Route path="/" element={<TeleCare />} />
          <Route path="/landing" element={<Landing />} />
          <Route path="/telecare" element={<TeleCare />} />
          <Route path="/registration" element={<Registration />} />
          <Route path="/login" element={<Login />} />
          <Route path="/request" element={<Request />} />
          {/*protected sites */}
          <Route
            path="/dashboard"
            element={
              <ProtectedRoute>
                <Dashboard />
              </ProtectedRoute>
            }
          />
          <Route
            path="/settings"
            element={
              <ProtectedRoute>
                <Settings />
              </ProtectedRoute>
            }
          />
          <Route
            path="/editUserAccount"
            element={
              <ProtectedRoute>
                <EditUserAccount />
              </ProtectedRoute>
            }
          />
        </Routes>
      </Router>
    </div>
  );
}

export default App;
