import { NextResponse } from "next/server";

export async function POST(req) {
  try {
 
    const response = NextResponse.json(
      {
        success: true,
        message: "You are logged out successfully"
      },
      { status: 200 }
    );

    response.cookies.set("ua", false);
      response.cookies.set("jwt", "", {
        httpOnly: true,
        maxAge: 0, 
        path: "/",  
      });

    return response;
  } catch (error) {
    console.error(error); // Fix typo here

    return NextResponse.json(
      {
        message: error?.message || "An error occurred",
      },
      { status: 500 }
    );
  }
}
