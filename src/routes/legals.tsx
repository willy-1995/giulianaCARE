import { useState } from "react";
import Footer from "./components/footer";
import Navbar from "./components/navbar";
import "./styles/request.scss";
import "./styles/main.scss";

const MAX_LENGTH = 500;

export default function Legal() {
  return (
    <div className="body-div legal-content">
      <Navbar />
      <div className="legal-div">
        <h2>Impressum</h2>
      </div>
      <div className="legal-div">
        <h2>Datenschutz</h2>
      </div>
      <div className="legal-div">
        <h2>Allgemeine Geschäftsbedingungen (AGB)</h2>
      </div>
      <Footer />
    </div>
  );
}
