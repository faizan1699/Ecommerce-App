
import "./globals.css";

import Context from "./components/context/page";
import Root from "./components/rootcomponent/page";

export const metadata = {
  title: "Ecommerce App",
  description: "this is ecommerce app",
};

export default function RootLayout({ children }) {

    return (
    <html lang="en">
      <body>
        <Context>
          <Root />
          {children}
        </Context>
      </body>
    </html>
  );
}
