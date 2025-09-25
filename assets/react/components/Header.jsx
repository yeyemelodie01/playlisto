import { Link } from "react-router-dom";

const Header = () => {
    return (
        <header className="navbar py-2">
            <Link to="/">
                <img src="/images/playlisto-logo.png" alt="playlisto logo" className="w-40 px-4 py-2"/>
            </Link>
        </header>
    )
}

export default Header;