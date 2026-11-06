import json
import logging
import math

import azure.functions as func

app = func.FunctionApp()


def is_nonnegative_number(value: object) -> bool:
    if isinstance(value, bool):
        return False
    if isinstance(value, int):
        return value >= 0
    return isinstance(value, float) and math.isfinite(value) and value >= 0


@app.function_name(name="CloudCartNewOrder")
@app.route(
    route="neworder",
    methods=["POST"],
    auth_level=func.AuthLevel.FUNCTION,
)
def new_order(req: func.HttpRequest) -> func.HttpResponse:
    try:
        order = req.get_json()
    except ValueError:
        return func.HttpResponse(
            json.dumps({"error": "Request body must be valid JSON."}),
            status_code=400,
            mimetype="application/json",
        )

    if not isinstance(order, dict):
        return func.HttpResponse(
            json.dumps({"error": "Request body must be a JSON object."}),
            status_code=400,
            mimetype="application/json",
        )

    order_id = order.get("order_id")
    total = order.get("total")
    items = order.get("items")
    if (
        isinstance(order_id, bool)
        or not isinstance(order_id, int)
        or order_id < 1
        or not isinstance(order.get("event_id"), str)
        or order.get("event_id") != f"cloudcart-order-{order_id}"
        or not isinstance(order.get("status"), str)
        or not order["status"].strip()
        or not is_nonnegative_number(total)
        or order.get("currency") != "GBP"
        or not isinstance(items, list)
        or not items
    ):
        return func.HttpResponse(
            json.dumps({"error": "Order details are invalid."}),
            status_code=400,
            mimetype="application/json",
        )

    for item in items:
        if (
            not isinstance(item, dict)
            or not isinstance(item.get("name"), str)
            or not item["name"].strip()
            or len(item["name"]) > 120
            or isinstance(item.get("quantity"), bool)
            or not isinstance(item.get("quantity"), int)
            or item["quantity"] < 1
            or not is_nonnegative_number(item.get("unit_price"))
        ):
            return func.HttpResponse(
                json.dumps({"error": "Order item details are invalid."}),
                status_code=400,
                mimetype="application/json",
            )

    logging.info(
        "Accepted CloudCart order event %s with %d items.",
        order["event_id"],
        len(items),
    )
    return func.HttpResponse(
        json.dumps({"accepted": True, "event_id": order["event_id"]}),
        status_code=202,
        mimetype="application/json",
    )
