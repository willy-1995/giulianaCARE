import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { BrowserRouter as Router, Routes, Route, Link } from "react-router-dom";
import Landing from "./routes/landing";
import TeleCare from "./routes/telecare";
import Registration from "./routes/registration";
import Login from "./routes/login";
import { ProtectedRoute } from "./routes/protectedRoutes";
import Dashboard from "./routes/dashboard";
import Settings from "./routes/settings";
import Request from "./routes/request";
import TermsAndConditions from "./routes/termsAndConditions";
import Feedback from "./routes/feedback";
import { Datasecurity } from "./routes/datasecurity";
import { Imprint } from "./routes/imprint";
import { Salespartnership } from "./routes/salespartnership";
import ResetPassword from "./routes/resetPassword";
import "./routes/styles/main.scss";
import { useEffect } from "react";
//Test
import { ClientSearch } from "./routes/TEST_SEARCH_CLIENT";

function App() {
  // Hier definieren wir die Zustände für Token und ID
  const [token, setToken] = useState<string | null>(
    localStorage.getItem("token"),
  );
  const [myId, setMyId] = useState<number | null>(
    localStorage.getItem("myId") ? Number(localStorage.getItem("myId")) : null,
  );

  useEffect(() => {
    document.title = "giulianaCARE";
  }, []);

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
          <Route path="/resetPassword" element={<ResetPassword />} />
          <Route path="/request" element={<Request />} />
          <Route path="/termsAndConditions" element={<TermsAndConditions />} />
          <Route path="/imprint" element={<Imprint />} />
          <Route path="/datasecurity" element={<Datasecurity />} />
          <Route path="/feedback" element={<Feedback />} />
          <Route path="/salespartnership" element={<Salespartnership />} />
          {/*TEST */}
          <Route path="/TEST_SEARCH_CLIENT" element={<ClientSearch />} />
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
        </Routes>
      </Router>
    </div>
  );
}

export default App;
