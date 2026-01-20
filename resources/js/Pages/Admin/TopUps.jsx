import React, { useState, useEffect } from "react";
import AdminLayout from "@/Layouts/AdminLayout";
import axios from "axios";
import { Elements, CardElement, useStripe, useElements } from "@stripe/react-stripe-js";
import { loadStripe } from "@stripe/stripe-js";
import Swal from "sweetalert2";

// Replace with your actual public key from Stripe
const stripePromise = loadStripe("pk_test_51S3N5rDXO6DeN2FzLZoKtX1WdAI7egygpJcLlpM0lC0xrUANM9fEWUI7vMBD3ClZhWj4DdVN9Hd8UOI4EX3EMobL00YUI2fuvu"); 

// Modal Component to display the Stripe form
const Modal = ({ isOpen, closeModal, selectedAgent, topUpAmount }) => {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-50">
      <div className="bg-white p-6 rounded-lg w-96">
        <h3 className="text-center text-xl font-semibold mb-4">Top-up Form</h3>
        <TopUpForm selectedAgent={selectedAgent} topUpAmount={topUpAmount} closeModal={closeModal} />
        <button
          onClick={closeModal}
          className="mt-4 block w-full text-center py-2 bg-red-500 text-white rounded-lg"
        >
          Close
        </button>
      </div>
    </div>
  );
};

// TopUpForm Component that will handle Stripe payment
function TopUpForm({ selectedAgent, topUpAmount, closeModal }) {
  const stripe = useStripe();
  const elements = useElements();

  const handlePayment = async () => {
    if (!stripe || !elements) {
      return;
    }

    try {
      const response = await axios.post(`/admin/topups/${selectedAgent}/stripe`, {
        amount: topUpAmount,
      });

      const { clientSecret, reference } = response.data;

      if (!clientSecret || !reference) {
        Swal.fire({
          icon: 'error',
          title: 'Payment failed',
          text: 'Missing necessary details.',
        });
        return;
      }

      const cardElement = elements.getElement(CardElement);

      const { error, paymentIntent } = await stripe.confirmCardPayment(clientSecret, {
        payment_method: {
          card: cardElement,
          billing_details: {
            name: "Cardholder Name",
          },
        },
      });

      if (error) {
        Swal.fire({
          icon: 'error',
          title: 'Payment failed',
          text: error.message,
        });
      } else if (paymentIntent.status === "succeeded") {
        Swal.fire({
          icon: 'success',
          title: 'Top-up successful!',
          text: 'Your payment was successful.',
        }).then(async () => {
          // Send the reference and status to the backend (no need for paymentIntentId)
          const updateResponse = await axios.post(`/admin/topups/${selectedAgent}/stripe-complete`, {
            reference: reference,   // Pass reference from top-up transaction
            status: 'succeeded',     // Payment status ('succeeded' or 'failed')
          });

          if (updateResponse.data.error) {
            Swal.fire({
              icon: 'error',
              title: 'Failed to update transaction status',
              text: updateResponse.data.error,
            });
          } else {
            // Optionally, handle additional tasks like updating the UI, etc.
          }
        });
      }
    } catch (error) {
      console.error("Error processing payment:", error);
      Swal.fire({
        icon: 'error',
        title: 'Something went wrong',
        text: 'An error occurred during the payment process.',
      });
    }
  };

  return (
    <div>
      <CardElement />
      <button
        onClick={handlePayment}
        type="button"
        disabled={!stripe}
        className="mt-4 inline-flex items-center justify-center rounded-xl bg-emerald-500/90 px-4 py-2 text-[12px] font-semibold text-black hover:bg-emerald-400"
      >
        Complete Top-up
      </button>
    </div>
  );
}

export default function TopUps({ active }) {
  const [agents, setAgents] = useState([]);
  const [topUpAmount, setTopUpAmount] = useState("");
  const [selectedAgent, setSelectedAgent] = useState(null);
  const [loading, setLoading] = useState(true);
  const [isModalOpen, setIsModalOpen] = useState(false);

  // Fetch agents list on component mount
  useEffect(() => {
    axios
      .get("/admin/agents")
      .then((response) => {
        setAgents(response.data.data);
        setLoading(false);
      })
      .catch((error) => {
        console.error("Error fetching agents:", error);
        setLoading(false);
      });
  }, []);

  // Function to handle opening the modal
  const openModal = () => {
    if (!selectedAgent || !topUpAmount) {
      Swal.fire({
        icon: 'warning',
        title: 'Missing Information',
        text: 'Please select an agent and enter an amount.',
      });
      return;
    }
    setIsModalOpen(true);
  };

  // Function to close the modal
  const closeModal = () => {
    setIsModalOpen(false);
  };

  return (
    <AdminLayout title="Agent Top-ups" active={active}>
      <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div className="flex flex-1 items-center gap-2">
            <select
              onChange={(e) => setSelectedAgent(e.target.value)}
              value={selectedAgent}
              className="w-full rounded-xl border border-white/10 bg-black/30 px-4 py-2 text-[12px] text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-white/10"
            >
              <option value="">Select an Agent</option>
              {Array.isArray(agents) && agents.length > 0 ? (
                agents.map((agent) => (
                  <option key={agent.id} value={agent.id}>
                    {agent.name} ({agent.email})
                  </option>
                ))
              ) : (
                <option>No agents available</option>
              )}
            </select>
          </div>

          <input
            type="number"
            value={topUpAmount}
            onChange={(e) => setTopUpAmount(e.target.value)}
            placeholder="Enter amount"
            className="rounded-xl border border-white/10 bg-black/30 px-4 py-2 text-[12px] text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-white/10"
          />
        </div>

        {/* Modal trigger button */}
        <button
          onClick={openModal}
          className="mt-4 inline-flex items-center justify-center rounded-xl bg-emerald-500/90 px-4 py-2 text-[12px] font-semibold text-black hover:bg-emerald-400"
        >
          New Top-up
        </button>
      </div>

      {/* Modal for Stripe Payment Form */}
      <Elements stripe={stripePromise}>
        <Modal
          isOpen={isModalOpen}
          closeModal={closeModal}
          selectedAgent={selectedAgent}
          topUpAmount={topUpAmount}
        />
      </Elements>

      <div className="mt-6 overflow-hidden rounded-xl border border-white/10">
        <table className="min-w-full text-left text-[12px]">
          <thead className="bg-white/5 text-white/45">
            <tr>
              <th className="px-4 py-3 font-semibold tracking-[0.20em] text-[10px]">AGENT</th>
              <th className="px-4 py-3 font-semibold tracking-[0.20em] text-[10px]">REFERENCE</th>
              <th className="px-4 py-3 font-semibold tracking-[0.20em] text-[10px]">AMOUNT</th>
              <th className="px-4 py-3 font-semibold tracking-[0.20em] text-[10px]">STATUS</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-white/10">
            {loading && (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-white/40">
                  Loading…
                </td>
              </tr>
            )}

            {!loading && agents.length === 0 && (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-white/40">
                  No agents available
                </td>
              </tr>
            )}

            {Array.isArray(agents) && agents.length > 0 ? (
              agents.map((agent) => (
                <tr key={agent.id} className="bg-black/20">
                  <td className="px-4 py-3 text-white/85">{agent.name}</td>
                  <td className="px-4 py-3 text-white/85">{`TOPUP-${agent.id}`}</td>
                  <td className="px-4 py-3 text-white/85">₱{agent.topUpAmount}</td>
                  <td className="px-4 py-3 text-white/85">
                    <span className="rounded-full bg-white/10 px-2 py-1 text-[11px] text-white/70 ring-1 ring-white/10">
                      {agent.status}
                    </span>
                  </td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-white/40">
                  No top-up transactions found
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </AdminLayout>
  );
}
