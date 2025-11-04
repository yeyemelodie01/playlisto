import { useState } from "react";
import { Link } from "react-router-dom";

const MenuAside = () => {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <>
            <button
                className={`fixed z-50 btn btn-primary transition-all duration-300 ml-0 ${isOpen ? "ml-64" : ""} btn_menu_aside`}

                onClick={() => setIsOpen(!isOpen)}
                aria-label="Ouvrir le menu"
            >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clipRule="evenodd" />
                </svg>
            </button>

            <div className="flex transition-all duration-300">
                <div className="relative w-0">
                    <div
                        className={`absolute left-0 inset-y-0 w-64 border-r border-black pointer-events-none ${
                            isOpen ? "translate-x-0" : "-translate-x-full"
                        } transform transition-transform duration-300`}
                    />
                    <div
                        className={`absolute left-0 inset-y-0 w-64 flex flex-col justify-between py-6 px-4 bg-base-200 overflow-y-auto pointer-events-auto transform transition-transform duration-300 ${
                            isOpen ? "translate-x-0" : "-translate-x-full"
                        }`}
                    >
                        <ul className="menu container h-full">
                            <li>
                                <Link to="/">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        className="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth="2"
                                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                    </svg>
                                    Accueil
                                </Link>
                            </li>
                            <li>
                                <Link to="/playlist">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        className="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth="2"
                                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                    Voir mes playlists
                                </Link>
                            </li>
                            <li>
                                <Link to="/question" className="btn btn-neutral">
                                    Générer une playlist
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </>
    );
};

export default MenuAside;